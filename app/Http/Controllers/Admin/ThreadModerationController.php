<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Thread;
use App\Models\ThreadReply;
use App\Models\ThreadReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Takedown for the forum.
 *
 * Threads post straight to the page — unlike wall submissions there is no
 * approval queue in front of them — so the queue that matters here is what
 * people reported after the fact. Nothing is ever deleted: hiding flips a
 * status so a thread can be put back if the call was wrong.
 */
class ThreadModerationController extends Controller
{
    public function reports(Request $request): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query('limit', 10)));

        $reports = ThreadReport::query()
            ->open()
            ->with([
                'thread:id,title,topic,status',
                'reply:id,thread_id,body,status',
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'items' => $reports->map(fn (ThreadReport $report) => [
                'id' => $report->id,
                'reason' => $report->reason,
                'created_at' => $report->created_at,
                'target' => $report->thread_reply_id ? 'reply' : 'thread',
                'thread' => $report->thread?->only(['id', 'title', 'topic', 'status']),
                'reply' => $report->reply?->only(['id', 'body', 'status']),
            ])->all(),
            'openCount' => ThreadReport::query()->open()->count(),
        ]);
    }

    public function hideThread(Thread $thread): JsonResponse
    {
        return $this->setStatus($thread, 'hidden');
    }

    public function restoreThread(Thread $thread): JsonResponse
    {
        return $this->setStatus($thread, 'visible');
    }

    public function hideReply(ThreadReply $reply): JsonResponse
    {
        return $this->setStatus($reply, 'hidden');
    }

    public function restoreReply(ThreadReply $reply): JsonResponse
    {
        return $this->setStatus($reply, 'visible');
    }

    /**
     * Acting on something closes every open report against it — the moderator
     * has now seen it, and leaving duplicates open buries the next real one.
     */
    public function resolve(ThreadReport $report): JsonResponse
    {
        $report->forceFill(['status' => 'resolved'])->save();

        return response()->json(['status' => 'resolved']);
    }

    private function setStatus(Thread|ThreadReply $target, string $status): JsonResponse
    {
        $target->forceFill(['status' => $status])->save();

        $reports = ThreadReport::query()->open();

        $target instanceof Thread
            ? $reports->where('thread_id', $target->id)
            : $reports->where('thread_reply_id', $target->id);

        $reports->update(['status' => 'resolved']);

        return response()->json(['status' => $status]);
    }
}
