<?php

namespace App\Http\Controllers;

use App\Models\JobListing;
use App\Models\JobMonitor;
use App\Models\JobMonitorHit;
use App\Models\JobSave;
use App\Services\Jobs\JobMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The student half of JobRadar: saved jobs (which double as the application
 * tracker), job monitors, and the matches those monitors found.
 */
class JobTrackerController extends Controller
{
    private const HIT_LIMIT = 60;

    public function mine(Request $request): Response
    {
        $user = $request->user();

        $saves = JobSave::query()
            ->where('user_id', $user->id)
            ->with('listing')
            ->latest('updated_at')
            ->get()
            ->map(fn (JobSave $save) => [
                'id' => $save->id,
                'status' => $save->status,
                'notes' => $save->notes,
                'applied_on' => $save->applied_on?->toDateString(),
                'follow_up_on' => $save->follow_up_on?->toDateString(),
                'updated_at' => $save->updated_at,
                'listing' => [...JobController::cardFor($save->listing), 'status' => $save->listing->status],
            ]);

        $monitors = JobMonitor::query()
            ->where('user_id', $user->id)
            ->withCount('hits')
            ->orderBy('id')
            ->get();

        $hits = JobMonitorHit::query()
            ->whereIn('job_monitor_id', $monitors->modelKeys())
            ->whereHas('listing', fn ($query) => $query->approved())
            ->with('listing', 'monitor:id,name')
            ->latest('id')
            ->limit(self::HIT_LIMIT)
            ->get();

        $payload = Inertia::render('Jobs/Mine', [
            'saves' => $saves->all(),
            'monitors' => $monitors->map(fn (JobMonitor $monitor) => [
                ...$monitor->only(['id', 'name', 'keywords', 'location', 'employment_types', 'remote_ok', 'min_score', 'frequency', 'email_alerts', 'is_active']),
                'hits_count' => $monitor->hits_count,
            ])->all(),
            'hits' => $hits->map(fn (JobMonitorHit $hit) => [
                'id' => $hit->id,
                'score' => $hit->score,
                'reasons' => $hit->reasons,
                'is_new' => $hit->seen_at === null,
                'monitor' => $hit->monitor->name,
                'created_at' => $hit->created_at,
                'listing' => JobController::cardFor($hit->listing),
            ])->all(),
            'savedIds' => $saves->pluck('listing.id')->all(),
            'statuses' => JobSave::STATUSES,
            'options' => [
                'types' => JobListing::EMPLOYMENT_TYPES,
                'frequencies' => JobMonitor::FREQUENCIES,
                'maxMonitors' => JobMonitor::MAX_PER_USER,
                'maxKeywords' => JobMonitor::MAX_KEYWORDS,
            ],
        ]);

        // Opening the page is what reading a notification means here. The
        // payload above was built first, so this visit still shows them as new.
        JobMonitorHit::query()
            ->whereIn('job_monitor_id', $monitors->modelKeys())
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);

        return $payload;
    }

    public function save(Request $request, JobListing $listing): RedirectResponse
    {
        abort_unless($listing->isApproved(), 404);

        JobSave::query()->firstOrCreate(['user_id' => $request->user()->id, 'job_listing_id' => $listing->id]);

        return back();
    }

    public function updateSave(Request $request, JobSave $save): RedirectResponse
    {
        abort_unless($save->user_id === $request->user()->id, 404);

        $save->update($request->validate([
            'status' => ['sometimes', Rule::in(array_keys(JobSave::STATUSES))],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'applied_on' => ['sometimes', 'nullable', 'date'],
            'follow_up_on' => ['sometimes', 'nullable', 'date'],
        ]));

        return back();
    }

    public function destroySave(Request $request, JobSave $save): RedirectResponse
    {
        abort_unless($save->user_id === $request->user()->id, 404);

        $save->delete();

        return back();
    }

    public function unsave(Request $request, JobListing $listing): RedirectResponse
    {
        JobSave::query()
            ->where('user_id', $request->user()->id)
            ->where('job_listing_id', $listing->id)
            ->delete();

        return back();
    }

    public function storeMonitor(Request $request, JobMatcher $matcher): RedirectResponse
    {
        if (JobMonitor::query()->where('user_id', $request->user()->id)->count() >= JobMonitor::MAX_PER_USER) {
            throw ValidationException::withMessages([
                'name' => 'You already have '.JobMonitor::MAX_PER_USER.' monitors. Remove one to add another.',
            ]);
        }

        $monitor = JobMonitor::create([...$this->monitorData($request), 'user_id' => $request->user()->id]);
        $found = $matcher->backfill($monitor);

        return back()->with('success', $found
            ? "Monitor saved. It already found {$found} matching job(s) from the last month."
            : 'Monitor saved. You will hear from it when something matches.');
    }

    public function updateMonitor(Request $request, JobMonitor $monitor, JobMatcher $matcher): RedirectResponse
    {
        abort_unless($monitor->user_id === $request->user()->id, 404);

        $monitor->update($this->monitorData($request));

        // Matches that no longer clear the new bar go, so the list reflects
        // what the monitor now asks for. Then look again under the new terms.
        $monitor->hits()->each(function (JobMonitorHit $hit) use ($matcher, $monitor) {
            if ($matcher->score($monitor, $hit->listing)['score'] < max(1, $monitor->min_score)) {
                $hit->delete();
            }
        });
        $matcher->backfill($monitor);

        return back()->with('success', 'Monitor updated.');
    }

    public function destroyMonitor(Request $request, JobMonitor $monitor): RedirectResponse
    {
        abort_unless($monitor->user_id === $request->user()->id, 404);

        $monitor->delete();

        return back();
    }

    /** @return array<string, mixed> */
    private function monitorData(Request $request): array
    {
        if (is_string($request->input('keywords'))) {
            $request->merge(['keywords' => preg_split('/[,\n]/', $request->input('keywords'))]);
        }

        $request->merge([
            'keywords' => array_values(array_unique(array_filter(array_map(
                fn ($keyword) => trim((string) $keyword),
                (array) $request->input('keywords', []),
            )))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'keywords' => ['required', 'array', 'min:1', 'max:'.JobMonitor::MAX_KEYWORDS],
            'keywords.*' => ['string', 'max:60'],
            'location' => ['nullable', 'string', 'max:120'],
            'employment_types' => ['nullable', 'array'],
            'employment_types.*' => [Rule::in(JobListing::EMPLOYMENT_TYPES)],
            'remote_ok' => ['boolean'],
            'min_score' => ['required', 'integer', 'min:20', 'max:100'],
            'frequency' => ['required', Rule::in(JobMonitor::FREQUENCIES)],
            'email_alerts' => ['boolean'],
            'is_active' => ['boolean'],
        ], [
            'keywords.required' => 'Add at least one job title or keyword.',
        ]);

        return [
            ...Arr::except($data, ['employment_types']),
            'employment_types' => array_values($data['employment_types'] ?? []) ?: null,
            'location' => filled($data['location'] ?? null) ? trim($data['location']) : null,
        ];
    }
}
