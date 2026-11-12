<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobListing;
use App\Models\JobSource;
use App\Services\Jobs\JobFetcher;
use App\Services\Jobs\JobMatcher;
use App\Services\Jobs\JobSafety;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The JobRadar queue and its sources. Listings arrive pending from shared
 * links and from untrusted feeds; approving one is what makes it public and
 * what lets monitors hear about it.
 */
class JobModerationController extends Controller
{
    public function page(): Response
    {
        return Inertia::render('Admin/Jobs', [
            'drivers' => JobSource::DRIVERS,
            'types' => JobListing::EMPLOYMENT_TYPES,
            'sectors' => JobListing::SECTORS,
        ]);
    }

    public function listings(Request $request): JsonResponse
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected', 'closed'], true)
            ? $request->query('status')
            : 'pending';
        $limit = min(50, max(1, (int) $request->query('limit', 15)));

        $query = JobListing::query()
            ->where('status', $status)
            ->with('sources:id,job_listing_id,label,url')
            ->withCount('saves');

        // Flagged ones first in the queue: those are the ones that need a
        // careful read rather than a glance.
        $status === 'pending'
            ? $query->orderByRaw('safety_flags IS NULL')->orderBy('discovered_at')
            : $query->orderByDesc('reviewed_at')->orderByDesc('id');

        $paginator = $query->paginate($limit, ['*'], 'page', (int) $request->query('page', 1));

        return response()->json([
            'items' => collect($paginator->items())->map(fn (JobListing $listing) => [
                ...$listing->only(['id', 'title', 'employer', 'location', 'is_remote', 'employment_type', 'sector', 'salary_text', 'description', 'apply_url', 'status']),
                'deadline' => $listing->deadline?->toDateString(),
                'discovered_at' => $listing->discovered_at,
                'community' => $listing->submitter_ip_hash !== null,
                'saves_count' => $listing->saves_count,
                'flags' => collect((array) $listing->safety_flags)->map(fn ($evidence, $kind) => [
                    'kind' => $kind,
                    'label' => JobSafety::LABELS[$kind] ?? $kind,
                    'evidence' => is_scalar($evidence) ? (string) $evidence : null,
                ])->values()->all(),
                'sources' => $listing->sources->map->only(['id', 'label', 'url'])->all(),
            ])->all(),
            'total' => $paginator->total(),
            'totalPages' => max(1, $paginator->lastPage()),
            'counts' => JobListing::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function approve(Request $request, JobListing $listing, JobMatcher $matcher): JsonResponse
    {
        $edits = $request->validate([
            'title' => ['sometimes', 'string', 'max:'.JobListing::MAX_TITLE_LENGTH],
            'employer' => ['sometimes', 'nullable', 'string', 'max:160'],
            'location' => ['sometimes', 'nullable', 'string', 'max:160'],
            'employment_type' => ['sometimes', 'nullable', Rule::in(JobListing::EMPLOYMENT_TYPES)],
            'sector' => ['sometimes', 'nullable', Rule::in(JobListing::SECTORS)],
            'salary_text' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $wasPublic = $listing->isApproved();

        // A moderator who approves a reported listing has looked at the
        // reports; the count starts over.
        $flags = (array) $listing->safety_flags;
        unset($flags['reported']);

        $listing->forceFill([...$edits, 'status' => 'approved', 'reviewed_at' => now(), 'safety_flags' => $flags ?: null])->save();

        $matched = $wasPublic ? 0 : $matcher->matchListing($listing);

        return response()->json(['status' => 'approved', 'matched' => $matched]);
    }

    public function reject(JobListing $listing): JsonResponse
    {
        $listing->forceFill(['status' => 'rejected', 'reviewed_at' => now()])->save();

        return response()->json(['status' => 'rejected']);
    }

    public function close(JobListing $listing): JsonResponse
    {
        $listing->forceFill(['status' => 'closed', 'reviewed_at' => now()])->save();

        return response()->json(['status' => 'closed']);
    }

    public function sources(): JsonResponse
    {
        return response()->json([
            'items' => JobSource::query()->withCount('seen')->orderBy('name')->get()->map(fn (JobSource $source) => [
                ...$source->only(['id', 'name', 'driver', 'config', 'is_active', 'auto_approve', 'last_status', 'last_error', 'last_found_count']),
                'last_fetched_at' => $source->last_fetched_at,
                'seen_count' => $source->seen_count,
            ])->all(),
        ]);
    }

    public function storeSource(Request $request, JobFetcher $fetcher): JsonResponse
    {
        $source = JobSource::create($this->sourceData($request, $fetcher));

        return response()->json(['id' => $source->id], 201);
    }

    public function updateSource(Request $request, JobSource $source, JobFetcher $fetcher): JsonResponse
    {
        $source->update($this->sourceData($request, $fetcher, $source));

        return response()->json(['id' => $source->id]);
    }

    public function destroySource(JobSource $source): JsonResponse
    {
        // Listings it found stay; their link rows just lose the source.
        $source->delete();

        return response()->json(['deleted' => true]);
    }

    public function fetchSource(JobSource $source, JobFetcher $fetcher): JsonResponse
    {
        $counts = $fetcher->run($source, force: true);
        $source->refresh();

        if ($counts === null) {
            return response()->json(['error' => $source->last_error ?? 'The source could not be read.'], 422);
        }

        return response()->json(['counts' => $counts, 'found' => $source->last_found_count]);
    }

    /** @return array<string, mixed> */
    private function sourceData(Request $request, JobFetcher $fetcher, ?JobSource $existing = null): array
    {
        $data = $request->validate([
            'name' => [$existing ? 'sometimes' : 'required', 'string', 'max:120'],
            'driver' => [$existing ? 'sometimes' : 'required', Rule::in(array_keys(JobSource::DRIVERS))],
            'config' => [$existing ? 'sometimes' : 'required', 'array'],
            'config.interval_minutes' => ['nullable', 'integer', 'min:30', 'max:10080'],
            'is_active' => ['sometimes', 'boolean'],
            'auto_approve' => ['sometimes', 'boolean'],
        ]);

        // Taken from the input rather than $data: with a rule on one nested key,
        // validate() hands back only that key and drops the rest of the config.
        if ($request->has('config')) {
            $config = array_filter((array) $request->input('config'), fn ($value) => $value !== null && $value !== '');

            foreach (['keywords', 'locations'] as $list) {
                if (isset($config[$list]) && is_string($config[$list])) {
                    $config[$list] = array_values(array_filter(array_map('trim', explode(',', $config[$list]))));
                }
            }

            // Checkboxes arrive as booleans; a cleared one is dropped rather
            // than stored as false, so "set" always means on.
            $config = array_filter($config, fn ($value) => $value !== false);

            $errors = $fetcher->driver($data['driver'] ?? $existing->driver)->validateConfig($config);

            if ($errors !== []) {
                throw ValidationException::withMessages(collect($errors)->mapWithKeys(fn ($message, $key) => ["config.{$key}" => $message])->all());
            }

            $data['config'] = $config;
        }

        return $data;
    }
}
