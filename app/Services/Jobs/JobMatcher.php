<?php

namespace App\Services\Jobs;

use App\Models\JobListing;
use App\Models\JobMonitor;
use App\Models\JobMonitorHit;
use Illuminate\Support\Str;

/**
 * How well one listing fits one monitor, out of 100, and why.
 *
 *   keywords        55  title says it (55), title has every word (45),
 *                       only the description does (20)
 *   location        20  no location asked, or it matches, or remote is fine
 *   employment type 15  none asked, or it matches; 7 when the listing is silent
 *   freshness       10  found within 3 days (10), a week (6), a month (3)
 *
 * Keywords are the gate: a listing that mentions none of them scores zero,
 * however close and fresh it is — "near you" is not a job you wanted.
 */
final class JobMatcher
{
    /** How far back a new monitor looks, so it is not empty on day one. */
    public const BACKFILL_DAYS = 30;

    /** @return array{score: int, reasons: list<string>} */
    public function score(JobMonitor $monitor, JobListing $listing): array
    {
        $title = ' '.JobText::normalize($listing->title).' ';
        $description = ' '.JobText::normalize($listing->description).' ';

        $keywordScore = 0;
        $keywordReason = null;

        foreach ((array) $monitor->keywords as $keyword) {
            $needle = JobText::normalize($keyword);

            if ($needle === '') {
                continue;
            }

            [$points, $reason] = match (true) {
                str_contains($title, " {$needle} ") => [55, 'title'],
                $this->hasEveryWord($title, $needle) => [45, 'title'],
                str_contains($description, " {$needle} ") => [20, 'description'],
                default => [0, null],
            };

            if ($points > $keywordScore) {
                $keywordScore = $points;
                $keywordReason = $reason;
            }
        }

        if ($keywordScore === 0) {
            return ['score' => 0, 'reasons' => []];
        }

        $reasons = [$keywordReason === 'title' ? 'Job title' : 'Mentioned in the description'];
        $score = $keywordScore;

        [$locationPoints, $locationReason] = $this->location($monitor, $listing);
        $score += $locationPoints;

        if ($locationReason) {
            $reasons[] = $locationReason;
        }

        $types = array_filter((array) $monitor->employment_types);

        if ($types === []) {
            $score += 15;
        } elseif ($listing->employment_type === null) {
            $score += 7;
        } elseif (in_array($listing->employment_type, $types, true)) {
            $score += 15;
            $reasons[] = 'Employment type';
        }

        $age = $listing->discovered_at?->diffInDays(now()) ?? 99;
        $score += match (true) {
            $age <= 3 => 10,
            $age <= 7 => 6,
            $age <= 30 => 3,
            default => 0,
        };

        if ($age <= 3) {
            $reasons[] = 'Newly posted';
        }

        return ['score' => min(100, $score), 'reasons' => $reasons];
    }

    /**
     * Record a hit on every active monitor this listing clears. Called when a
     * listing first becomes visible, so a monitor hears about it once.
     */
    public function matchListing(JobListing $listing): int
    {
        if (! $listing->isApproved()) {
            return 0;
        }

        $created = 0;

        JobMonitor::query()->active()->each(function (JobMonitor $monitor) use ($listing, &$created) {
            $created += (int) $this->record($monitor, $listing);
        });

        return $created;
    }

    /**
     * Fill a new or edited monitor from what is already up. These count as
     * already emailed: the student is looking at them on the page right now,
     * and a first digest of thirty old jobs reads as spam.
     */
    public function backfill(JobMonitor $monitor): int
    {
        $created = 0;

        JobListing::query()
            ->approved()
            ->where('discovered_at', '>=', now()->subDays(self::BACKFILL_DAYS))
            ->each(function (JobListing $listing) use ($monitor, &$created) {
                $created += (int) $this->record($monitor, $listing, emailed: true);
            });

        return $created;
    }

    private function record(JobMonitor $monitor, JobListing $listing, bool $emailed = false): bool
    {
        ['score' => $score, 'reasons' => $reasons] = $this->score($monitor, $listing);

        if ($score === 0 || $score < $monitor->min_score) {
            return false;
        }

        $hit = JobMonitorHit::query()->firstOrCreate(
            ['job_monitor_id' => $monitor->id, 'job_listing_id' => $listing->id],
            ['score' => $score, 'reasons' => $reasons, 'emailed_at' => $emailed ? now() : null],
        );

        return $hit->wasRecentlyCreated;
    }

    /** @return array{0: int, 1: ?string} */
    private function location(JobMonitor $monitor, JobListing $listing): array
    {
        if (blank($monitor->location)) {
            return [20, null];
        }

        if ($listing->is_remote) {
            return $monitor->remote_ok ? [20, 'Remote'] : [0, null];
        }

        $where = ' '.JobText::normalize($listing->location).' ';

        // "Baguio City and Benguet" asks for either place, not both.
        $places = preg_split('/,|;|\/|\band\b|\bor\b/i', (string) $monitor->location) ?: [];

        foreach ($places as $place) {
            $place = trim(preg_replace('/\b(city|province|municipality)\b/', '', JobText::normalize($place)) ?? '');

            if ($place !== '' && str_contains($where, " {$place}")) {
                return [20, 'Location'];
            }
        }

        // Unknown is not wrong, so it keeps a little credit.
        return blank($listing->location) ? [5, null] : [0, null];
    }

    private function hasEveryWord(string $haystack, string $needle): bool
    {
        $words = array_filter(explode(' ', $needle), fn ($word) => Str::length($word) > 1);

        if (count($words) < 2) {
            return false;
        }

        foreach ($words as $word) {
            if (! str_contains($haystack, " {$word} ")) {
                return false;
            }
        }

        return true;
    }
}
