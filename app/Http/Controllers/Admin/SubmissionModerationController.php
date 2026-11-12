<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Services\VisitCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionModerationController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('Admin/Dashboard');
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');
        $limit = min(50, max(1, (int) $request->query('limit', 8)));

        $query = Submission::query()->where('status', $status);

        if ($status === 'approved') {
            $query->orderByDesc('reviewed_at');
        } else {
            // Flagged first, then oldest first. A post naming a classmate and
            // printing her number should not wait behind a day of clean ones.
            $query->byRisk();
        }

        $paginator = $query
            ->paginate($limit, ['*'], 'page', (int) $request->query('page', 1));

        $paginator->getCollection()->each->append('risk_level');

        return response()->json([
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'limit' => $limit,
            'totalPages' => max(1, $paginator->lastPage()),
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'pending' => Submission::query()->pending()->count(),
            'approvedToday' => Submission::query()->approved()->whereDate('reviewed_at', today())->count(),
            'rejectedToday' => Submission::query()->where('status', 'rejected')->whereDate('reviewed_at', today())->count(),
        ]);
    }

    /** Unique visitors: headline numbers, today's sections, and 30 days. */
    public function visits(VisitCounter $counter): JsonResponse
    {
        return response()->json($counter->adminStats());
    }

    public function approve(Request $request, Submission $submission): JsonResponse
    {
        if ($submission->status !== 'pending') {
            return response()->json(['error' => 'Submission has already been reviewed'], 400);
        }

        $editedContent = is_string($request->input('content')) ? trim($request->input('content')) : null;

        $this->markApproved($submission, $editedContent, now());

        return response()->json(['status' => 'approved']);
    }

    /**
     * Clear the whole pending queue in one pass. Edits still open in the
     * dashboard come along in `edits`, keyed by submission id, so nothing an
     * admin typed is lost by reaching for this instead of approving one by one.
     */
    public function approveAll(Request $request): JsonResponse
    {
        $edits = collect(is_array($request->input('edits')) ? $request->input('edits') : [])
            ->filter(fn ($content) => is_string($content))
            ->mapWithKeys(fn ($content, $id) => [(int) $id => trim($content)]);

        // Snapshot the queue first. A submission that lands while this runs has
        // not been read by anyone, so it waits for the next pass instead of
        // being swept through unreviewed.
        $ids = Submission::query()->pending()->orderBy('submitted_at')->pluck('id');
        $reviewedAt = now();
        $approved = 0;

        foreach ($ids->chunk(100) as $chunk) {
            DB::transaction(function () use ($chunk, $edits, $reviewedAt, &$approved) {
                $submissions = Submission::query()
                    ->pending()
                    ->whereIn('id', $chunk)
                    ->lockForUpdate()
                    ->get();

                foreach ($submissions as $submission) {
                    $this->markApproved($submission, $edits->get($submission->id), $reviewedAt);
                    $approved++;
                }
            });
        }

        return response()->json(['status' => 'approved', 'approved' => $approved]);
    }

    /**
     * Why a post was turned down is worth a column of its own. "Rejected"
     * alone lumps a bullying call-out in with a duplicate and a canteen menu,
     * and a pile of that teaches nothing later — see moderator.md, tier 2.
     */
    public const REJECTION_REASONS = ['bullying', 'privacy', 'hate', 'sexual', 'spam', 'off_topic', 'other'];

    public function reject(Request $request, Submission $submission): JsonResponse
    {
        if ($submission->status !== 'pending') {
            return response()->json(['error' => 'Submission has already been reviewed'], 400);
        }

        $reason = $request->input('reason');

        $submission->status = 'rejected';
        $submission->rejection_reason = in_array($reason, self::REJECTION_REASONS, true) ? $reason : 'other';
        $submission->reviewed_at = now();
        $submission->save();

        return response()->json(['status' => 'rejected']);
    }

    private function markApproved(Submission $submission, ?string $editedContent, Carbon $reviewedAt): void
    {
        $submission->status = 'approved';
        $submission->was_edited = (bool) $editedContent && $editedContent !== $submission->content;
        $submission->content = $editedContent ?: $submission->content;
        $submission->reviewed_at = $reviewedAt;
        $submission->save();
    }
}
