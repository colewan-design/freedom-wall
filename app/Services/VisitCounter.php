<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Counts unique visitors: each person once per day, per section of the site.
 *
 * A visitor is a random id kept in a first-party cookie for a year, stored
 * only as a keyed hash. Clearing cookies makes someone a new visitor, which
 * is the honest limit of counting people on a site nobody has to sign in to.
 */
class VisitCounter
{
    public const COOKIE = 'fw_vid';

    public const COOKIE_MINUTES = 60 * 24 * 400;

    /** First path segment => the section it is counted under. */
    public const SECTIONS = [
        '' => 'wall',
        'wall' => 'wall',
        'threads' => 'threads',
        'chat' => 'chat',
        'jobs' => 'jobs',
        'id-check' => 'id-check',
        'id' => 'id-check',
        'tools' => 'tools',
        'apps' => 'tools',
        'feed' => 'feed',
        'profile' => 'feed',
        'friends' => 'feed',
        'messages' => 'feed',
        'journal' => 'feed',
        'saved' => 'feed',
    ];

    public const LABELS = [
        'wall' => 'News Feed',
        'threads' => 'Threads',
        'chat' => 'Chat',
        'jobs' => 'Jobs',
        'id-check' => 'ID Check',
        'tools' => 'Tools',
        'feed' => 'Student feed',
    ];

    private const STATS_CACHE_KEY = 'visits:public-stats';

    /** Days are counted in Philippine time, not the server's UTC. */
    public const TIMEZONE = 'Asia/Manila';

    /**
     * Crawlers, link unfurlers and headless browsers. They are not people and
     * a share in a group chat would otherwise count once per preview bot.
     */
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|whatsapp|telegram|discord|skype|curl|wget|python|httpclient|okhttp|go-http|java\/|headless|lighthouse|pingdom|uptime|monitor|scrapy|axios|node-fetch/i';

    /** The section this request belongs to, or null when it is not a page view. */
    public function sectionFor(Request $request): ?string
    {
        if (! $request->isMethod('GET')) {
            return null;
        }

        // Polling and JSON endpoints are not visits; Inertia navigations are.
        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return null;
        }

        // An Inertia partial reload (a filter, a save) is the same visit.
        if ($request->header('X-Inertia-Partial-Data')) {
            return null;
        }

        $agent = (string) $request->userAgent();

        if ($agent === '' || preg_match(self::BOT_PATTERN, $agent)) {
            return null;
        }

        return self::SECTIONS[(string) $request->segment(1)] ?? null;
    }

    /** The visitor's id, minting one when the request did not carry it. */
    public function visitorId(Request $request): array
    {
        $id = (string) $request->cookie(self::COOKIE);

        if (Str::isUuid($id)) {
            return [$id, false];
        }

        return [(string) Str::uuid(), true];
    }

    /** Today's date where our readers are. */
    public function day(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }

    public function record(string $visitorId, string $section): void
    {
        // Keyed with the app key, so the stored value cannot be matched back
        // to a cookie without the server's secret.
        $hash = hash_hmac('sha256', $visitorId, (string) config('app.key'));

        DB::table('site_visits')->insertOrIgnore([
            'visited_on' => $this->day()->toDateString(),
            'section' => $section,
            'visitor' => $hash,
            'created_at' => now(),
        ]);
    }

    /**
     * The numbers the public footer shows. Cached briefly: they are read on
     * every page and nobody needs them to the second.
     *
     * @return array{today: int, total: int}
     */
    public function publicStats(): array
    {
        return Cache::remember(self::STATS_CACHE_KEY, now()->addMinutes(5), fn () => [
            'today' => $this->uniqueSince($this->day()),
            'total' => (int) DB::table('site_visits')->distinct()->count('visitor'),
        ]);
    }

    /** @return array<string, mixed> */
    public function adminStats(): array
    {
        $since30 = $this->day()->subDays(29);

        $daily = DB::table('site_visits')
            ->where('visited_on', '>=', $since30->toDateString())
            ->selectRaw('visited_on, COUNT(DISTINCT visitor) as visitors')
            ->groupBy('visited_on')
            ->orderBy('visited_on')
            ->pluck('visitors', 'visited_on');

        $days = [];

        for ($day = $since30; $day->lte($this->day()); $day = $day->addDay()) {
            $key = $day->toDateString();
            $days[] = ['date' => $key, 'visitors' => (int) ($daily[$key] ?? 0)];
        }

        $sections = DB::table('site_visits')
            ->where('visited_on', $this->day()->toDateString())
            ->selectRaw('section, COUNT(DISTINCT visitor) as visitors')
            ->groupBy('section')
            ->orderByDesc('visitors')
            ->pluck('visitors', 'section');

        return [
            'today' => $this->uniqueSince($this->day()),
            'yesterday' => (int) DB::table('site_visits')->where('visited_on', $this->day()->subDay()->toDateString())->distinct()->count('visitor'),
            'last7' => $this->uniqueSince($this->day()->subDays(6)),
            'last30' => $this->uniqueSince($since30),
            'total' => (int) DB::table('site_visits')->distinct()->count('visitor'),
            'sections' => $sections->map(fn ($visitors, $section) => [
                'section' => $section,
                'label' => self::LABELS[$section] ?? $section,
                'visitors' => (int) $visitors,
            ])->values()->all(),
            'days' => $days,
        ];
    }

    private function uniqueSince(\DateTimeInterface $from): int
    {
        return (int) DB::table('site_visits')
            ->where('visited_on', '>=', $from->format('Y-m-d'))
            ->distinct()
            ->count('visitor');
    }
}
