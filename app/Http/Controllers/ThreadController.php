<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreThreadReplyRequest;
use App\Http\Requests\StoreThreadRequest;
use App\Models\Thread;
use App\Models\ThreadReply;
use App\Models\ThreadReplyVote;
use App\Models\ThreadReport;
use App\Services\ContentFilterService;
use App\Services\IpHasher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The community forum: anonymous threads with nested replies.
 *
 * Nobody signs in here, so a visitor is identified by a token kept in their
 * session. It is enough to mark their own posts back to them and to stop one
 * person voting on the same reply twice; it is not an account, and clearing
 * cookies makes someone a new visitor. Anything moderation needs to act on
 * across sessions rides on the hashed IP instead.
 */
class ThreadController extends Controller
{
    private const VISITOR_KEY = 'thread_visitor';

    private const VIEWED_KEY = 'thread_viewed';

    private const THREAD_LIST_LIMIT = 20;

    private const TRENDING_LIMIT = 5;

    public function index(Request $request): Response
    {
        $open = Thread::query()->visible()->recentlyActive()->first();

        return $this->page($request, $open);
    }

    public function show(Request $request, Thread $thread): Response
    {
        abort_unless($thread->isVisible(), 404);

        return $this->page($request, $thread);
    }

    public function store(
        StoreThreadRequest $request,
        ContentFilterService $contentFilter,
        IpHasher $ipHasher,
    ): RedirectResponse {
        $title = trim($request->string('title'));
        $body = trim($request->string('body'));

        // The title is what the sidebar and the trending list render, so it has
        // to clear the filter on its own — not just as part of the body.
        if ($contentFilter->containsBlockedContent($title)) {
            throw ValidationException::withMessages([
                'title' => 'Your title contains content that is not allowed.',
            ]);
        }

        if ($contentFilter->containsBlockedContent($body)) {
            throw ValidationException::withMessages([
                'body' => 'Your thread contains content that is not allowed.',
            ]);
        }

        $thread = Thread::create([
            'title' => $title,
            'body' => $body,
            'topic' => $request->string('topic')->toString(),
            'author_token' => $this->visitorToken($request),
            'ip_hash' => $ipHasher->hash($request->ip()),
            'last_activity_at' => now(),
        ]);

        return redirect()->route('threads.show', $thread);
    }

    public function storeReply(
        StoreThreadReplyRequest $request,
        Thread $thread,
        ContentFilterService $contentFilter,
        IpHasher $ipHasher,
    ): JsonResponse {
        abort_unless($thread->isVisible(), 404);

        $body = trim($request->string('body'));

        if ($contentFilter->containsBlockedContent($body)) {
            throw ValidationException::withMessages([
                'body' => 'Your reply contains content that is not allowed.',
            ]);
        }

        [$parentId, $depth] = $this->placeReply($thread, $request->input('parent_id'));

        $reply = ThreadReply::create([
            'thread_id' => $thread->id,
            'parent_id' => $parentId,
            'depth' => $depth,
            'body' => $body,
            'author_token' => $this->visitorToken($request),
            'ip_hash' => $ipHasher->hash($request->ip()),
        ]);

        $thread->touchActivity();

        return response()->json([
            'item' => [
                'id' => $reply->id,
                'parent_id' => $reply->parent_id,
                'depth' => $reply->depth,
                'removed' => false,
                'body' => $reply->body,
                'created_at' => $reply->created_at,
                'score' => 0,
                'your_vote' => 0,
                'is_yours' => true,
                'children' => [],
            ],
            'replyCount' => $thread->replies()->visible()->count(),
        ]);
    }

    public function vote(Request $request, ThreadReply $reply): JsonResponse
    {
        abort_unless($reply->isVisible(), 404);

        $validated = $request->validate([
            'value' => ['required', 'integer', 'in:1,-1'],
        ]);

        $value = (int) $validated['value'];
        $token = $this->visitorToken($request);

        $existing = ThreadReplyVote::query()
            ->where('thread_reply_id', $reply->id)
            ->where('voter_token', $token)
            ->first();

        if ($existing && $existing->value === $value) {
            // Pressing the arrow you already pressed takes the vote back.
            $existing->delete();
            $yours = 0;
        } else {
            ThreadReplyVote::updateOrCreate(
                ['thread_reply_id' => $reply->id, 'voter_token' => $token],
                ['value' => $value],
            );
            $yours = $value;
        }

        return response()->json([
            'score' => (int) $reply->votes()->sum('value'),
            'your_vote' => $yours,
        ]);
    }

    public function reportThread(Request $request, Thread $thread, IpHasher $ipHasher): JsonResponse
    {
        abort_unless($thread->isVisible(), 404);

        return $this->fileReport($request, $ipHasher, $thread->id, null);
    }

    public function reportReply(Request $request, ThreadReply $reply, IpHasher $ipHasher): JsonResponse
    {
        abort_unless($reply->isVisible(), 404);

        return $this->fileReport($request, $ipHasher, $reply->thread_id, $reply->id);
    }

    private function fileReport(Request $request, IpHasher $ipHasher, int $threadId, ?int $replyId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:'.implode(',', ThreadReport::REASONS)],
        ]);

        $token = $this->visitorToken($request);

        // One open report per visitor per target: a second press is the same
        // person, and a queue full of duplicates hides the real ones.
        $existing = ThreadReport::query()
            ->open()
            ->where('reporter_token', $token)
            ->where('thread_id', $threadId)
            ->when($replyId === null,
                fn ($query) => $query->whereNull('thread_reply_id'),
                fn ($query) => $query->where('thread_reply_id', $replyId),
            )
            ->first();

        $report = $existing ?? ThreadReport::create([
            'thread_id' => $threadId,
            'thread_reply_id' => $replyId,
            'reason' => $validated['reason'],
            'reporter_token' => $token,
            'ip_hash' => $ipHasher->hash($request->ip()),
        ]);

        return response()->json(['reported' => true, 'id' => $report->id]);
    }

    private function page(Request $request, ?Thread $thread): Response
    {
        if ($thread) {
            $this->recordView($request, $thread);
        }

        return Inertia::render('Threads/Index', [
            'thread' => $thread ? $this->threadPayload($request, $thread) : null,
            'threads' => $this->threadList(),
            'trending' => $this->trending(),
            'topics' => Thread::TOPICS,
            'activeTags' => $this->activeTags(),
            'reportReasons' => ThreadReport::REASONS,
            'threadStats' => [
                'totalThreads' => Thread::query()->visible()->count(),
                'totalReplies' => ThreadReply::query()->visible()->count(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function threadPayload(Request $request, Thread $thread): array
    {
        return [
            'id' => $thread->id,
            'title' => $thread->title,
            'body' => $thread->body,
            'topic' => $thread->topic,
            'created_at' => $thread->created_at,
            'views_count' => $thread->views_count,
            'reply_count' => $thread->replies()->visible()->count(),
            'is_yours' => $thread->author_token === $this->visitorToken($request),
            'replies' => $this->replyTree($request, $thread),
        ];
    }

    /**
     * The whole conversation, nested. A thread is small enough to load in one
     * query and assemble in memory — cheaper and simpler than a recursive
     * query, and the indent cap keeps the recursion three deep.
     *
     * Taken-down replies are loaded along with the rest rather than filtered
     * out in SQL. Dropping one here would orphan every reply underneath it,
     * and those answers would vanish from the page while still sitting in the
     * database — so a hidden reply that still has a conversation under it
     * stays as a marker, with its text and its author left behind.
     *
     * @return list<array<string, mixed>>
     */
    private function replyTree(Request $request, Thread $thread): array
    {
        $rows = $thread->replies()
            ->withSum('votes as score', 'value')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $token = $this->visitorToken($request);

        $myVotes = ThreadReplyVote::query()
            ->where('voter_token', $token)
            ->whereIn('thread_reply_id', $rows->pluck('id'))
            ->pluck('value', 'thread_reply_id');

        // Grouped on 0 rather than null: a null grouping key comes back as ''
        // from Collection::groupBy, which is a sharp edge worth stepping around.
        $byParent = $rows->groupBy(fn (ThreadReply $reply) => $reply->parent_id ?? 0);

        $build = function (int $parentId) use (&$build, $byParent, $myVotes, $token): array {
            $nodes = [];

            foreach ($byParent->get($parentId, new Collection) as $reply) {
                $children = $build($reply->id);
                $removed = ! $reply->isVisible();

                // A marker earns its place only while something is still
                // hanging off it. A taken-down reply with nothing underneath
                // goes entirely.
                if ($removed && $children === []) {
                    continue;
                }

                $nodes[] = [
                    'id' => $reply->id,
                    'parent_id' => $reply->parent_id,
                    'depth' => $reply->depth,
                    'removed' => $removed,
                    'body' => $removed ? null : $reply->body,
                    'created_at' => $reply->created_at,
                    'score' => $removed ? 0 : (int) ($reply->score ?? 0),
                    'your_vote' => $removed ? 0 : (int) ($myVotes[$reply->id] ?? 0),
                    'is_yours' => ! $removed && $reply->author_token === $token,
                    'children' => $children,
                ];
            }

            return $nodes;
        };

        return $build(0);
    }

    /** @return list<array<string, mixed>> */
    private function threadList(): array
    {
        return Thread::query()
            ->visible()
            ->withCount(['replies as reply_count' => fn ($query) => $query->visible()])
            ->recentlyActive()
            ->limit(self::THREAD_LIST_LIMIT)
            ->get(['id', 'title', 'topic', 'views_count', 'last_activity_at'])
            ->map(fn (Thread $thread) => [
                'id' => $thread->id,
                'title' => $thread->title,
                'topic' => $thread->topic,
                'views_count' => $thread->views_count,
                'reply_count' => $thread->reply_count,
                'last_activity_at' => $thread->last_activity_at,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function trending(): array
    {
        return Thread::query()
            ->visible()
            ->withCount(['replies as reply_count' => fn ($query) => $query->visible()])
            ->orderByDesc('views_count')
            ->orderByDesc('id')
            ->limit(self::TRENDING_LIMIT)
            ->get(['id', 'title', 'views_count'])
            ->map(fn (Thread $thread) => [
                'id' => $thread->id,
                'title' => $thread->title,
                'views_count' => $thread->views_count,
                'reply_count' => $thread->reply_count,
            ])
            ->all();
    }

    /**
     * Topics that actually have threads behind them, busiest first — the
     * curated list is what the composer offers, this is what people chose.
     *
     * @return list<string>
     */
    private function activeTags(): array
    {
        return Thread::query()
            ->visible()
            ->groupBy('topic')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(12)
            ->pluck('topic')
            ->all();
    }

    /**
     * Where a reply lands. Answering something already at the indent cap makes
     * the new reply a sibling of it rather than a fourth level, so the
     * conversation continues instead of erroring out.
     *
     * @return array{0: ?int, 1: int}
     */
    private function placeReply(Thread $thread, mixed $requestedParentId): array
    {
        if (blank($requestedParentId)) {
            return [null, 0];
        }

        $parent = $thread->replies()->visible()->find((int) $requestedParentId);

        if (! $parent) {
            return [null, 0];
        }

        if ($parent->depth >= ThreadReply::MAX_DEPTH) {
            return [$parent->parent_id, $parent->depth];
        }

        return [$parent->id, $parent->depth + 1];
    }

    /**
     * Views are counted once per session per thread. Without accounts there is
     * nothing better to dedupe on, and a raw hit counter would just measure
     * how often someone refreshed.
     */
    private function recordView(Request $request, Thread $thread): void
    {
        $seen = (array) $request->session()->get(self::VIEWED_KEY, []);

        if (in_array($thread->id, $seen, true)) {
            return;
        }

        $thread->increment('views_count');
        $request->session()->put(self::VIEWED_KEY, [...$seen, $thread->id]);
    }

    private function visitorToken(Request $request): string
    {
        if ($request->session()->has(self::VISITOR_KEY)) {
            return (string) $request->session()->get(self::VISITOR_KEY);
        }

        $token = (string) Str::uuid();
        $request->session()->put(self::VISITOR_KEY, $token);

        return $token;
    }
}
