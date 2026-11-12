<?php

namespace App\Services\Jobs;

use App\Models\JobListing;
use App\Models\JobListingSource;
use App\Models\JobSource;
use Illuminate\Support\Facades\DB;

/**
 * Turns what a driver read into listings, without duplicating any.
 *
 * Each posting is looked up three ways, in order: by the source's own id (we
 * have read this exact posting before), then by fingerprint (another source
 * has the same vacancy — attach this one as an extra link), and only then is a
 * new listing created.
 */
final class JobIngestor
{
    /** A feed can be long; nobody needs more than this per read. */
    public const MAX_PER_FETCH = 200;

    /** A feed posting not seen for this long is taken as filled. */
    public const STALE_AFTER_DAYS = 14;

    public function __construct(
        private readonly JobSafety $safety,
        private readonly JobMatcher $matcher,
    ) {}

    /**
     * @param  list<NormalizedJob>  $jobs
     * @return array{new: int, merged: int, seen: int}
     */
    public function ingest(JobSource $source, array $jobs): array
    {
        $counts = ['new' => 0, 'merged' => 0, 'seen' => 0];

        foreach (array_slice($jobs, 0, self::MAX_PER_FETCH) as $job) {
            $outcome = DB::transaction(fn () => $this->ingestOne($source, $job));
            $counts[$outcome]++;
        }

        return $counts;
    }

    /**
     * A link pasted in by a reader. Merges into an existing listing when the
     * vacancy is already up, so a popular post does not appear five times.
     *
     * @param  array<string, mixed>  $data  validated fields from the form
     */
    public function submit(array $data, ?int $userId, string $ipHash): JobListing
    {
        return DB::transaction(function () use ($data, $userId, $ipHash) {
            $fingerprint = JobFingerprint::for($data['title'], $data['employer'] ?? null);
            $existing = $this->openListingWithFingerprint($fingerprint);
            $label = (string) (parse_url($data['apply_url'], PHP_URL_HOST) ?: 'Shared link');

            if ($existing) {
                $alreadyLinked = $existing->sources()->where('url', $data['apply_url'])->exists();

                if (! $alreadyLinked) {
                    $existing->sources()->create(['url' => $data['apply_url'], 'label' => $label]);
                }

                return $existing;
            }

            $listing = new JobListing([
                ...$data,
                'is_remote' => (bool) ($data['is_remote'] ?? false),
                'sector' => $data['sector'] ?? JobText::sector($data['employer'] ?? null, $data['apply_url']),
                'status' => 'pending',
                'fingerprint' => $fingerprint,
                'discovered_at' => now(),
                'submitted_by_user_id' => $userId,
                'submitter_ip_hash' => $ipHash,
            ]);

            $listing->safety_flags = $this->safety->assess($listing) ?: null;
            $listing->save();

            $listing->sources()->create(['url' => $data['apply_url'], 'label' => $label]);

            return $listing;
        });
    }

    /**
     * Close what has quietly gone away: deadlines that passed, and feed-only
     * listings no feed has shown for two weeks. Community links have no feed
     * to vanish from, so only their deadline can close them.
     */
    public function closeStale(): int
    {
        $closed = JobListing::query()
            ->whereIn('status', ['approved', 'pending'])
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<', today())
            ->update(['status' => 'closed']);

        $cutoff = now()->subDays(self::STALE_AFTER_DAYS);

        $closed += JobListing::query()
            ->whereIn('status', ['approved', 'pending'])
            ->whereDoesntHave('sources', fn ($query) => $query->whereNull('job_source_id'))
            ->whereDoesntHave('sources', fn ($query) => $query->where('last_seen_at', '>=', $cutoff))
            ->whereHas('sources')
            ->update(['status' => 'closed']);

        return $closed;
    }

    /** @return 'new'|'merged'|'seen' */
    private function ingestOne(JobSource $source, NormalizedJob $job): string
    {
        $known = JobListingSource::query()
            ->where('job_source_id', $source->id)
            ->where('external_id', $job->externalId)
            ->first();

        if ($known) {
            $known->forceFill(['last_seen_at' => now(), 'url' => $job->url])->save();

            return 'seen';
        }

        $fingerprint = JobFingerprint::for($job->title, $job->employer);
        $listing = $this->openListingWithFingerprint($fingerprint);
        $outcome = 'merged';

        if ($listing) {
            $this->enrich($listing, $job);
        } else {
            $listing = $this->create($source, $job, $fingerprint);
            $outcome = 'new';
        }

        $listing->sources()->create([
            'job_source_id' => $source->id,
            'external_id' => $job->externalId,
            'url' => $job->url,
            'label' => $source->name,
        ]);

        if ($outcome === 'new' && $listing->isApproved()) {
            $this->matcher->matchListing($listing);
        }

        return $outcome;
    }

    private function create(JobSource $source, NormalizedJob $job, string $fingerprint): JobListing
    {
        $flags = $this->safety->assess($job);

        // Trusted feeds skip the queue — unless something looks off, in which
        // case a human sees it first like everything else.
        $status = $source->auto_approve && $flags === [] ? 'approved' : 'pending';

        $listing = JobListing::create([
            'title' => $job->title,
            'employer' => $job->employer,
            'location' => $job->location,
            'is_remote' => $job->isRemote,
            'employment_type' => $job->employmentType,
            'sector' => JobText::sector($job->employer, $job->url),
            'salary_text' => $job->salaryText,
            'description' => $job->description,
            'apply_url' => $job->url,
            'published_at' => $job->publishedAt,
            'discovered_at' => now(),
            'status' => $status,
            'fingerprint' => $fingerprint,
            'safety_flags' => $flags ?: null,
        ]);

        if ($status === 'approved') {
            $listing->forceFill(['reviewed_at' => now()])->save();
        }

        return $listing;
    }

    /** A second source fills gaps the first left; it never overwrites. */
    private function enrich(JobListing $listing, NormalizedJob $job): void
    {
        $fill = array_filter([
            'location' => blank($listing->location) ? $job->location : null,
            'employment_type' => blank($listing->employment_type) ? $job->employmentType : null,
            'salary_text' => blank($listing->salary_text) ? $job->salaryText : null,
            'description' => blank($listing->description) ? $job->description : null,
            'published_at' => $listing->published_at === null ? $job->publishedAt : null,
        ], fn ($value) => $value !== null);

        if ($fill !== []) {
            $listing->forceFill($fill)->save();
        }
    }

    private function openListingWithFingerprint(string $fingerprint): ?JobListing
    {
        return JobListing::query()
            ->where('fingerprint', $fingerprint)
            ->whereIn('status', ['approved', 'pending'])
            ->latest('id')
            ->first();
    }
}
