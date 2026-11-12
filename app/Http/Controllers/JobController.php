<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobListingRequest;
use App\Models\JobListing;
use App\Models\JobSave;
use App\Models\JobSource;
use App\Services\ContentFilterService;
use App\Services\IpHasher;
use App\Services\Jobs\JobIngestor;
use App\Services\Jobs\JobSafety;
use App\Services\TurnstileService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * JobRadar's public side: the board, a listing's page, and the "share a job"
 * form. Anyone can read and share; saving and monitors need a student account
 * and live in JobTrackerController.
 */
class JobController extends Controller
{
    private const PER_PAGE = 20;

    private const REPORTED_KEY = 'jobs_reported';

    /** Reports from different sessions before a live listing goes back to the queue. */
    private const REPORTS_TO_PULL = 3;

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $listings = $this->filtered(JobListing::query()->approved(), $filters)
            ->latestDiscovered()
            ->withCount('sources')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $savedIds = $this->student($request)
            ? JobSave::query()->where('user_id', $request->user()->id)->pluck('job_listing_id')->all()
            : [];

        return Inertia::render('Jobs/Index', [
            'listings' => [
                'data' => $listings->getCollection()->map(fn (JobListing $listing) => $this->card($listing))->all(),
                'page' => $listings->currentPage(),
                'lastPage' => $listings->lastPage(),
                'total' => $listings->total(),
                'prevUrl' => $listings->previousPageUrl(),
                'nextUrl' => $listings->nextPageUrl(),
            ],
            'filters' => $filters,
            'savedIds' => $savedIds,
            'canTrack' => $this->student($request) !== null,
            'options' => [...$this->options(), 'employers' => $this->employers()],
            'insights' => $this->insights(),
        ]);
    }

    public function show(Request $request, JobListing $listing): Response
    {
        // Closed listings stay readable — someone may have saved one — but
        // pending and rejected ones were never public.
        abort_unless(in_array($listing->status, ['approved', 'closed'], true), 404);

        $listing->load('sources');
        $save = $this->student($request)
            ? JobSave::query()->where('user_id', $request->user()->id)->where('job_listing_id', $listing->id)->first()
            : null;

        return Inertia::render('Jobs/Show', [
            'listing' => [
                ...$this->card($listing),
                'description' => $listing->description,
                'published_at' => $listing->published_at,
                'missing' => $listing->missingFields(),
                'status' => $listing->status,
                'sources' => $listing->sources->map(fn ($source) => [
                    'id' => $source->id,
                    'label' => $source->label,
                    'url' => $source->url,
                    'first_seen_at' => $source->first_seen_at,
                ])->all(),
            ],
            'save' => $save?->only(['id', 'status', 'notes', 'applied_on', 'follow_up_on']),
            'canTrack' => $this->student($request) !== null,
            'statuses' => JobSave::STATUSES,
            'alreadyReported' => in_array($listing->id, (array) $request->session()->get(self::REPORTED_KEY, []), true),
            'similar' => $this->similar($listing),
            'options' => $this->options(),
        ]);
    }

    public function store(
        StoreJobListingRequest $request,
        ContentFilterService $contentFilter,
        TurnstileService $turnstile,
        IpHasher $ipHasher,
        JobIngestor $ingestor,
    ): RedirectResponse {
        $data = $request->listingData();

        foreach (['title', 'employer', 'description'] as $field) {
            if (filled($data[$field] ?? null) && $contentFilter->containsBlockedContent($data[$field])) {
                throw ValidationException::withMessages([$field => 'This contains content that is not allowed.']);
            }
        }

        // Signed-in students have already proved they are people.
        if (! $this->student($request) && ! $turnstile->verify($request->input('captchaToken'), $request->ip())) {
            throw ValidationException::withMessages(['captchaToken' => 'Please complete the CAPTCHA challenge.']);
        }

        $listing = $ingestor->submit($data, $request->user()?->id, $ipHasher->hash($request->ip()));

        $message = $listing->isApproved()
            ? 'That job is already on the board — your link was added to it as another source.'
            : 'Thanks! A moderator checks every shared job before it goes up.';

        return redirect()->route('jobs.index')->with('success', $message);
    }

    /**
     * A reader thinks this posting is a scam or gone. Enough separate reports
     * pull it back into the queue until a moderator looks again.
     */
    public function report(Request $request, JobListing $listing): JsonResponse
    {
        abort_unless($listing->isApproved(), 404);

        $request->validate(['reason' => ['required', Rule::in(['scam', 'closed', 'wrong-info', 'spam'])]]);

        $reported = (array) $request->session()->get(self::REPORTED_KEY, []);

        if (! in_array($listing->id, $reported, true)) {
            $flags = (array) $listing->safety_flags;
            $count = (int) ($flags['reported'] ?? 0) + 1;
            $flags['reported'] = $count;

            $listing->forceFill([
                'safety_flags' => $flags,
                'status' => $count >= self::REPORTS_TO_PULL ? 'pending' : $listing->status,
            ])->save();

            $request->session()->put(self::REPORTED_KEY, [...$reported, $listing->id]);
        }

        return response()->json(['reported' => true]);
    }

    /** @return array<string, mixed> */
    public static function cardFor(JobListing $listing): array
    {
        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'employer' => $listing->employer,
            'location' => $listing->location,
            'is_remote' => $listing->is_remote,
            'employment_type' => $listing->employment_type,
            'sector' => $listing->sector,
            'salary_text' => $listing->salary_text,
            'deadline' => $listing->deadline?->toDateString(),
            'discovered_at' => $listing->discovered_at,
            'apply_url' => $listing->apply_url,
            'excerpt' => $listing->description ? Str::limit(preg_replace('/\s+/', ' ', $listing->description), 220) : null,
            'source_count' => $listing->sources_count ?? null,
            'warnings' => collect((array) $listing->safety_flags)
                ->keys()
                ->map(fn ($kind) => JobSafety::LABELS[$kind] ?? $kind)
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function card(JobListing $listing): array
    {
        return self::cardFor($listing);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $type = $request->query('type');
        $sector = $request->query('sector');
        $posted = (int) $request->query('posted');

        return [
            'q' => Str::limit(trim((string) $request->query('q', '')), 100, ''),
            'location' => Str::limit(trim((string) $request->query('location', '')), 100, ''),
            'type' => in_array($type, JobListing::EMPLOYMENT_TYPES, true) ? $type : null,
            'sector' => in_array($sector, JobListing::SECTORS, true) ? $sector : null,
            'remote' => $request->boolean('remote'),
            'posted' => in_array($posted, [1, 7, 30], true) ? $posted : null,
            'employer' => Str::limit(trim((string) $request->query('employer', '')), 160, ''),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function filtered(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] !== '', function (Builder $query) use ($filters) {
                // Every word has to appear somewhere, so "web developer" does
                // not return every job that says "developer".
                foreach (preg_split('/\s+/', $filters['q']) as $word) {
                    $like = '%'.addcslashes($word, '%_\\').'%';
                    $query->where(fn (Builder $q) => $q
                        ->where('title', 'like', $like)
                        ->orWhere('employer', 'like', $like)
                        ->orWhere('description', 'like', $like));
                }
            })
            ->when($filters['location'] !== '', function (Builder $query) use ($filters) {
                $query->where(fn (Builder $q) => $q
                    ->where('location', 'like', '%'.addcslashes($filters['location'], '%_\\').'%')
                    ->when($filters['remote'], fn (Builder $q) => $q->orWhere('is_remote', true)));
            })
            ->when($filters['remote'] && $filters['location'] === '', fn (Builder $q) => $q->where('is_remote', true))
            ->when($filters['type'], fn (Builder $q, $type) => $q->where('employment_type', $type))
            ->when($filters['sector'], fn (Builder $q, $sector) => $q->where('sector', $sector))
            // Exact, not LIKE: the value comes from the list of employers on
            // the board, and "Acme" should not also pull in "Acme Logistics".
            ->when($filters['employer'] !== '', fn (Builder $q) => $q->where('employer', $filters['employer']))
            ->when($filters['posted'], fn (Builder $q, $days) => $q->where('discovered_at', '>=', now()->subDays($days)));
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'types' => JobListing::EMPLOYMENT_TYPES,
            'sectors' => JobListing::SECTORS,
        ];
    }

    /**
     * Every employer with an open job, government agencies first (the reason
     * most students filter by employer at all), then by how many openings.
     *
     * @return list<array{name: string, total: int, government: bool}>
     */
    private function employers(): array
    {
        return JobListing::query()
            ->approved()
            ->whereNotNull('employer')
            ->selectRaw("employer, COUNT(*) as total, MAX(CASE WHEN sector = 'government' THEN 1 ELSE 0 END) as government")
            ->groupBy('employer')
            ->orderByDesc('government')
            ->orderByDesc('total')
            ->orderBy('employer')
            ->limit(300)
            ->get()
            ->map(fn ($row) => ['name' => $row->employer, 'total' => (int) $row->total, 'government' => (bool) $row->government])
            ->all();
    }

    /**
     * What the board itself shows — the open listings we hold, not the job
     * market. The page says so next to the numbers.
     *
     * @return array<string, mixed>
     */
    private function insights(): array
    {
        $open = JobListing::query()->approved();

        return [
            'open' => (clone $open)->count(),
            'thisWeek' => (clone $open)->where('discovered_at', '>=', now()->subWeek())->count(),
            'remote' => (clone $open)->where('is_remote', true)->count(),
            'government' => (clone $open)->where('sector', 'government')->count(),
            'sources' => JobSource::query()->active()->count(),
            'topLocations' => (clone $open)
                ->whereNotNull('location')
                ->where('is_remote', false)
                ->selectRaw('location, COUNT(*) as total')
                ->groupBy('location')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(fn ($row) => ['label' => $row->location, 'total' => (int) $row->total])
                ->all(),
            'topEmployers' => (clone $open)
                ->whereNotNull('employer')
                ->selectRaw('employer, COUNT(*) as total')
                ->groupBy('employer')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(fn ($row) => ['label' => $row->employer, 'total' => (int) $row->total])
                ->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function similar(JobListing $listing): array
    {
        $words = collect(preg_split('/\s+/', Str::lower($listing->title)))
            ->filter(fn ($word) => Str::length($word) > 3)
            ->take(3);

        if ($words->isEmpty()) {
            return [];
        }

        return JobListing::query()
            ->approved()
            ->whereKeyNot($listing->id)
            ->where(fn (Builder $q) => $words->each(fn ($word) => $q->orWhere('title', 'like', '%'.addcslashes($word, '%_\\').'%')))
            ->latestDiscovered()
            ->limit(4)
            ->get()
            ->map(fn (JobListing $similar) => $this->card($similar))
            ->all();
    }

    /** The signed-in, verified student, or null for everyone else. */
    private function student(Request $request): ?\App\Models\User
    {
        $user = $request->user();

        return $user && $user->role === 'student' && $user->hasVerifiedEmail() ? $user : null;
    }
}
