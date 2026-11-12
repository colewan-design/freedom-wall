<?php

namespace App\Http\Middleware;

use App\Services\VisitCounter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records a page view for the unique-visitor counter.
 *
 * The session remembers which sections were already counted today, so a
 * visitor clicking around costs one insert per section per day rather than
 * one per page. Counting never gets to break a page: any failure is logged
 * and the response goes out as it was.
 */
class CountUniqueVisit
{
    private const SEEN_KEY = 'visits_counted';

    public function __construct(private readonly VisitCounter $counter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $section = $this->counter->sectionFor($request);

        // Moderators working the queue are not the audience being counted.
        if ($section === null || $request->user()?->role === 'admin') {
            return $response;
        }

        try {
            [$visitorId, $isNew] = $this->counter->visitorId($request);

            if ($isNew) {
                Cookie::queue(Cookie::make(VisitCounter::COOKIE, $visitorId, VisitCounter::COOKIE_MINUTES, sameSite: 'lax'));
            }

            $today = $this->counter->day()->toDateString();
            $mark = $today.'|'.$section;
            $seen = (array) $request->session()->get(self::SEEN_KEY, []);

            if (! in_array($mark, $seen, true) || $isNew) {
                $this->counter->record($visitorId, $section);

                // Only today's marks are worth keeping.
                $seen = array_values(array_filter($seen, fn ($entry) => str_starts_with($entry, $today)));
                $request->session()->put(self::SEEN_KEY, [...$seen, $mark]);
            }
        } catch (Throwable $e) {
            Log::warning('Visit counter failed', ['error' => $e->getMessage()]);
        }

        return $response;
    }
}
