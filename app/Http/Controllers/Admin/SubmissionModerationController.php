<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Services\FacebookPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

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

        $paginator = Submission::query()
            ->where('status', $status)
            ->orderBy('submitted_at', 'asc')
            ->paginate($limit, ['*'], 'page', (int) $request->query('page', 1));

        return response()->json([
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'limit' => $limit,
            'totalPages' => max(1, $paginator->lastPage()),
        ]);
    }

    public function failedFacebook(Request $request): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query('limit', 8)));

        $paginator = Submission::query()
            ->failedFacebook()
            ->orderBy('reviewed_at', 'asc')
            ->paginate($limit, ['*'], 'page', (int) $request->query('page', 1));

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

    public function approve(Request $request, Submission $submission, FacebookPageService $facebook): JsonResponse
    {
        if ($submission->status !== 'pending') {
            return response()->json(['error' => 'Submission has already been reviewed'], 400);
        }

        $editedContent = is_string($request->input('content')) ? trim($request->input('content')) : null;
        $finalContent = $editedContent ?: $submission->content;
        $wasEdited = $editedContent && $editedContent !== $submission->content;

        $submission->status = 'approved';
        $submission->content = $finalContent;
        $submission->was_edited = $wasEdited;
        $submission->reviewed_at = now();
        $submission->save();

        try {
            $fbPostId = $facebook->post($finalContent, $submission->image_urls);
            $submission->fb_post_id = $fbPostId;
            $submission->save();

            return response()->json(['status' => 'approved', 'fb_post_id' => $fbPostId]);
        } catch (RuntimeException $e) {
            // Post stays approved (visible on the site feed) but flagged for manual FB retry.
            return response()->json(['status' => 'approved', 'fb_post_id' => null, 'fb_error' => $e->getMessage()]);
        }
    }

    public function reject(Submission $submission): JsonResponse
    {
        if ($submission->status !== 'pending') {
            return response()->json(['error' => 'Submission has already been reviewed'], 400);
        }

        $submission->status = 'rejected';
        $submission->reviewed_at = now();
        $submission->save();

        return response()->json(['status' => 'rejected']);
    }

    public function retryFacebook(Submission $submission, FacebookPageService $facebook): JsonResponse
    {
        if ($submission->status !== 'approved') {
            return response()->json(['error' => 'No approved submission awaiting an FB post found'], 404);
        }

        try {
            $fbPostId = $facebook->post($submission->content, $submission->image_urls);
            $submission->fb_post_id = $fbPostId;
            $submission->save();

            return response()->json(['fb_post_id' => $fbPostId]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}
