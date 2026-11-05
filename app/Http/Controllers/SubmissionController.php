<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubmissionRequest;
use App\Models\Submission;
use App\Models\SubmissionVote;
use App\Rules\MediaAttachment;
use App\Services\ContentFilterService;
use App\Services\ContentRiskService;
use App\Services\IpHasher;
use App\Services\TurnstileService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
    private const WALL_POST_LIMIT = 50;

    /**
     * Who cast a vote. Nobody signs in here, so a voter is a browser: a token
     * in a year-long cookie, mirrored into the session so the first response
     * of a visit can use it before the cookie comes back. Paired with the
     * unique index on (submission_id, voter_token), a browser holds at most
     * one vote per post however many times the arrows are pressed.
     */
    private const VOTER_COOKIE = 'fw_voter';

    private const VOTER_KEY = 'wall_voter';

    private const VOTER_COOKIE_MINUTES = 60 * 24 * 365;

    public function store(
        StoreSubmissionRequest $request,
        ContentFilterService $contentFilter,
        ContentRiskService $contentRisk,
        TurnstileService $turnstile,
        IpHasher $ipHasher,
    ): RedirectResponse {
        $content = trim($request->string('content'));

        if ($contentFilter->containsBlockedContent($content)) {
            throw ValidationException::withMessages([
                'content' => 'Your submission contains content that is not allowed.',
            ]);
        }

        if (! $turnstile->verify($request->input('captchaToken'), $request->ip())) {
            throw ValidationException::withMessages([
                'captchaToken' => 'CAPTCHA verification failed. Please try again.',
            ]);
        }

        $content = $this->withCategoryHashtag($content, $request->string('category')->toString());

        $mediaUrls = [];
        foreach ($request->file('images', []) as $file) {
            $filename = Str::uuid().'.'.MediaAttachment::extensionFor($file);
            $path = $file->storeAs('uploads', $filename, 'public');
            $mediaUrls[] = Storage::disk('public')->url($path);
        }

        // Advisory only — the submission is stored and queued either way. All
        // the score decides is where in the queue an admin meets it.
        $risk = $contentRisk->assess($content);

        Submission::create([
            'content' => $content,
            'image_url' => $mediaUrls[0] ?? null,
            'images' => $mediaUrls ?: null,
            'ip_hash' => $ipHasher->hash($request->ip()),
            'risk_score' => $risk->score,
            'risk_flags' => $risk->isClean() ? null : $risk->flags,
            'risk_checked_at' => now(),
        ]);

        if ($risk->has('self_harm')) {
            return redirect()->route('wall')
                ->with('success', 'Submitted! Our team will review it before posting.')
                ->with('support', config('moderation.support'));
        }

        return redirect()->route('wall')->with('success', 'Submitted! Our team will review it before posting.');
    }

    public function wall(Request $request): Response
    {
        // select() before withSum(): passing columns to get() instead would be
        // ignored once the aggregate has put a select on the query, and the
        // rows would come back holding nothing but the score.
        $posts = Submission::query()
            ->approved()
            ->orderByDesc('reviewed_at')
            ->limit(self::WALL_POST_LIMIT)
            ->select(['id', 'content', 'image_url', 'images', 'reviewed_at'])
            ->withSum('votes as score', 'value')
            ->get();

        $scored = $this->scoredPosts();

        // One lookup for the feed and the leaderboard together: a post can sit
        // in both, and asking twice would only disagree with itself.
        $yourVotes = SubmissionVote::query()
            ->whereIn('submission_id', $posts->pluck('id')->merge($scored->pluck('id'))->unique())
            ->where('voter_token', $this->voterToken($request))
            ->pluck('value', 'submission_id');

        $posts->each(function (Submission $post) use ($yourVotes) {
            // withSum leaves null on a post nobody has voted on.
            $post->score = (int) ($post->score ?? 0);
            $post->your_vote = (int) ($yourVotes[$post->id] ?? 0);
        });

        return Inertia::render('Wall', [
            'posts' => $posts,
            'categories' => Submission::CATEGORIES,
            'stats' => $this->wallStats(),
            'postBoard' => $this->postBoard($scored, $yourVotes),
            'voteTotal' => SubmissionVote::query()->count(),
            // The hashtag directory in the sidebar.
            'tagCounts' => $this->tagCounts(),
        ]);
    }

    /**
     * Every post anyone has voted on, best score first.
     *
     * Pulled in one go and bucketed into the leaderboard's time windows in
     * PHP: four windowed queries would each drag the same vote subquery
     * across the whole wall. whereHas keeps it to posts with votes, which the
     * unique index on submission_votes answers from the index alone.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Submission>
     */
    private function scoredPosts(): \Illuminate\Database\Eloquent\Collection
    {
        return Submission::query()
            ->approved()
            ->whereHas('votes')
            ->select(['id', 'content', 'reviewed_at'])
            ->withSum('votes as score', 'value')
            ->orderByDesc('score')
            ->orderByDesc('reviewed_at')
            // A guard, not a product rule: the board shows six, and no window
            // needs more candidates than this to fill them.
            ->limit(500)
            ->get();
    }

    /**
     * The leaderboard: the best-scoring posts, by when they were posted.
     *
     * Only posts in positive territory make it — a board that listed posts on
     * zero would be ranking nothing, and one that listed negatives would be
     * pointing at the posts people liked least.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Submission>  $scored
     * @param  \Illuminate\Support\Collection<int, int>  $yourVotes
     * @return array<string, list<array<string, mixed>>>
     */
    private function postBoard($scored, $yourVotes): array
    {
        $windows = [
            'today' => now()->startOfDay(),
            'week' => now()->subDays(7),
            'month' => now()->subDays(30),
            'all' => null,
        ];

        $board = [];

        foreach ($windows as $window => $since) {
            $board[$window] = $scored
                ->filter(function (Submission $post) use ($since) {
                    if ((int) ($post->score ?? 0) <= 0) {
                        return false;
                    }

                    return $since === null
                        || ($post->reviewed_at !== null && $post->reviewed_at->gte($since));
                })
                ->take(6)
                ->map(fn (Submission $post) => [
                    'id' => $post->id,
                    'content' => $post->content,
                    'reviewed_at' => $post->reviewed_at,
                    'score' => (int) $post->score,
                    'your_vote' => (int) ($yourVotes[$post->id] ?? 0),
                ])
                ->values()
                ->all();
        }

        return $board;
    }

    /**
     * One vote per browser per post, up or down.
     *
     * Pressing the arrow already lit takes the vote back, and pressing the
     * other one flips it — either way the browser still owns a single row, so
     * no amount of clicking moves a post's score by more than one.
     */
    public function vote(Request $request, Submission $submission): JsonResponse
    {
        abort_unless($submission->status === 'approved', 404);

        $validated = $request->validate([
            'value' => ['required', 'integer', 'in:1,-1'],
        ]);

        $value = (int) $validated['value'];
        $token = $this->voterToken($request);

        $existing = SubmissionVote::query()
            ->where('submission_id', $submission->id)
            ->where('voter_token', $token)
            ->first();

        if ($existing && $existing->value === $value) {
            $existing->delete();
            $yours = 0;
        } else {
            SubmissionVote::updateOrCreate(
                ['submission_id' => $submission->id, 'voter_token' => $token],
                ['value' => $value],
            );
            $yours = $value;
        }

        return response()->json([
            'score' => (int) $submission->votes()->sum('value'),
            'your_vote' => $yours,
        ]);
    }

    /**
     * The browser's vote identity. Read from the cookie first so it survives
     * the session window; the session copy covers the request that mints it.
     *
     * Clearing cookies hands someone a fresh identity. The alternative — tying
     * a vote to the hashed IP — would stop that, but it would also let the
     * first student on the campus network speak for everyone behind it, so the
     * cookie is the honest trade.
     */
    private function voterToken(Request $request): string
    {
        $token = (string) $request->cookie(self::VOTER_COOKIE, '');

        if ($token === '') {
            $token = (string) $request->session()->get(self::VOTER_KEY, '');
        }

        if ($token === '') {
            $token = (string) Str::uuid();
        }

        $request->session()->put(self::VOTER_KEY, $token);
        Cookie::queue(self::VOTER_COOKIE, $token, self::VOTER_COOKIE_MINUTES);

        return $token;
    }

    /**
     * Counted straight from the database rather than from $posts, which only
     * holds the most recent WALL_POST_LIMIT rows — the totals describe the whole
     * wall, not the slice currently rendered.
     */
    private function wallStats(): array
    {
        $total = Submission::query()->approved()->count();

        $withPhotos = Submission::query()
            ->approved()
            ->where(function (Builder $query) {
                $query
                    ->whereJsonLength('images', '>', 0)
                    ->orWhere(function (Builder $legacy) {
                        // Rows predating the images column still carry a single image_url.
                        $legacy->whereNotNull('image_url')->where('image_url', '!=', '');
                    });
            })
            ->count();

        $today = Submission::query()
            ->approved()
            ->where('reviewed_at', '>=', now()->startOfDay())
            ->count();

        return [
            'total' => $total,
            'withPhotos' => $withPhotos,
            'textOnly' => $total - $withPhotos,
            'today' => $today,
        ];
    }

    /**
     * How many approved posts carry each hashtag, for the sidebar directory.
     *
     * The tag lives in the post body rather than in a column (see
     * withCategoryHashtag), so this counts in PHP over two columns instead of
     * reaching for MySQL string functions the SQLite test database lacks.
     *
     * @return list<array{tag: string, count: int}>
     */
    private function tagCounts(): array
    {
        $tally = [];

        Submission::query()
            ->approved()
            ->select(['id', 'content'])
            ->chunkById(500, function ($submissions) use (&$tally) {
                foreach ($submissions as $submission) {
                    $tag = $this->tagOf($submission->content);

                    if ($tag !== null) {
                        $tally[$tag] = ($tally[$tag] ?? 0) + 1;
                    }
                }
            });

        // Count first, then alphabetically, so a tie renders the same order on
        // every request instead of shuffling with whatever the database returns.
        uksort($tally, fn (string $a, string $b) => [$tally[$b], $a] <=> [$tally[$a], $b]);

        $ranked = [];
        foreach (array_slice($tally, 0, 12, true) as $tag => $count) {
            $ranked[] = ['tag' => $tag, 'count' => $count];
        }

        return $ranked;
    }

    /**
     * The trailing hashtag a post was filed under, lowercased. Hashtags that
     * appear mid-sentence are the author's own and are left out of the counts.
     */
    private function tagOf(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        return preg_match('/#([\p{L}\p{N}_-]{1,32})\s*$/u', $content, $matches) === 1
            ? mb_strtolower($matches[1])
            : null;
    }

    /**
     * Stamp the chosen category hashtag onto the end of the post. Posts that
     * already end with it (a resubmit, or someone who typed it themselves) are
     * left alone so the tag never doubles up.
     */
    private function withCategoryHashtag(string $content, string $category): string
    {
        $hashtag = '#'.$category;
        $content = rtrim($content);

        if (preg_match('/(^|\s)'.preg_quote($hashtag, '/').'\s*$/i', $content) === 1) {
            return $content;
        }

        return $content === '' ? $hashtag : $content."\n\n".$hashtag;
    }
}
