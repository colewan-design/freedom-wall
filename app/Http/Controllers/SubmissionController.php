<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubmissionRequest;
use App\Models\Submission;
use App\Models\SubmissionVote;
use App\Rules\MediaAttachment;
use App\Services\ContentFilterService;
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

        Submission::create([
            'content' => $content,
            'image_url' => $mediaUrls[0] ?? null,
            'images' => $mediaUrls ?: null,
            'ip_hash' => $ipHasher->hash($request->ip()),
        ]);

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

        $yourVotes = SubmissionVote::query()
            ->whereIn('submission_id', $posts->pluck('id'))
            ->where('voter_token', $this->voterToken($request))
            ->pluck('value', 'submission_id');

        $posts->each(function (Submission $post) use ($yourVotes) {
            // withSum leaves null on a post nobody has voted on.
            $post->score = (int) ($post->score ?? 0);
            $post->your_vote = (int) ($yourVotes[$post->id] ?? 0);
        });

        $tags = $this->tagBreakdown();

        return Inertia::render('Wall', [
            'posts' => $posts,
            'categories' => Submission::CATEGORIES,
            'stats' => $this->wallStats(),
            // Feeds the leaderboard's four time tabs and the hashtag directory
            // in the sidebar. Both read the same tally, counted once.
            'tagBoard' => $tags['board'],
            'tagCounts' => $tags['counts'],
            'tagTotals' => $tags['totals'],
        ]);
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
     * Tally how many approved posts carry each hashtag, both for the whole
     * wall and for the three recent windows the leaderboard tabs offer.
     *
     * The tag lives in the post body rather than in a column (see
     * withCategoryHashtag), so this counts in PHP over two columns instead of
     * reaching for MySQL string functions the SQLite test database lacks.
     *
     * @return array{
     *     board: array<string, list<array{tag: string, count: int}>>,
     *     counts: list<array{tag: string, count: int}>,
     *     totals: array<string, int>
     * }
     */
    private function tagBreakdown(): array
    {
        $windows = [
            'today' => now()->startOfDay(),
            'week' => now()->subDays(7),
            'month' => now()->subDays(30),
            'all' => null,
        ];

        $tallies = array_fill_keys(array_keys($windows), []);

        Submission::query()
            ->approved()
            ->select(['id', 'content', 'reviewed_at'])
            ->chunkById(500, function ($submissions) use ($windows, &$tallies) {
                foreach ($submissions as $submission) {
                    $tag = $this->tagOf($submission->content);

                    if ($tag === null) {
                        continue;
                    }

                    foreach ($windows as $window => $since) {
                        if ($since !== null && ($submission->reviewed_at === null || $submission->reviewed_at->lt($since))) {
                            continue;
                        }

                        $tallies[$window][$tag] = ($tallies[$window][$tag] ?? 0) + 1;
                    }
                }
            });

        $board = array_map(fn (array $tally) => $this->rankTags($tally, 6), $tallies);

        // A tag can top one window's list without placing in the all-time
        // twelve the sidebar shows, so ship the lifetime figure for every tag
        // the leaderboard can put on screen. Without this a tag new today
        // would report today's count as its total.
        $totals = [];
        foreach ($board as $rows) {
            foreach ($rows as $row) {
                $totals[$row['tag']] = $tallies['all'][$row['tag']] ?? $row['count'];
            }
        }

        return [
            'board' => $board,
            'counts' => $this->rankTags($tallies['all'], 12),
            'totals' => $totals,
        ];
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
     * @param  array<string, int>  $tally
     * @return list<array{tag: string, count: int}>
     */
    private function rankTags(array $tally, int $limit): array
    {
        // Count first, then alphabetically, so a tie renders the same order on
        // every request instead of shuffling with whatever the database returns.
        uksort($tally, fn (string $a, string $b) => [$tally[$b], $a] <=> [$tally[$a], $b]);

        $ranked = [];
        foreach (array_slice($tally, 0, $limit, true) as $tag => $count) {
            $ranked[] = ['tag' => $tag, 'count' => $count];
        }

        return $ranked;
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
