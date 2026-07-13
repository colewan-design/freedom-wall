<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            $query->orderBy('submitted_at', 'asc');
        }

        $paginator = $query
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

    public function approve(Request $request, Submission $submission): JsonResponse
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

        return response()->json(['status' => 'approved']);
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
}
