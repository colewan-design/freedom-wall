<?php

namespace App\Services\Jobs\Drivers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/** The plumbing every driver shares: one polite GET, and date parsing. */
abstract class HttpDriver implements JobSourceDriver
{
    /** More than any job feed should be; a bigger body is not one. */
    private const MAX_BYTES = 5_000_000;

    public function minimumIntervalMinutes(): int
    {
        return 60;
    }

    /** @param array<string, scalar> $query */
    protected function get(string $url, array $query = [], string $accept = 'application/json'): Response
    {
        try {
            $response = Http::timeout(20)
                ->connectTimeout(8)
                ->withUserAgent('BSUFreedomWall-JobRadar/1.0 (+'.config('app.url').'/jobs)')
                ->accept($accept)
                ->get($url, $query);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not connect: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("The source answered HTTP {$response->status()}.");
        }

        if (strlen($response->body()) > self::MAX_BYTES) {
            throw new RuntimeException('The response was too large to be a job feed.');
        }

        return $response;
    }

    /**
     * Whether a posting is somewhere our readers can take it. `locations` in
     * the config lists terms ("Philippines", "Asia", "Worldwide"); a posting
     * is kept when its location mentions any of them. Postings that state no
     * location at all are kept, since leaving it out usually means "anywhere".
     *
     * @param  array<string, mixed>  $config
     */
    protected function wantedLocation(?string $location, array $config): bool
    {
        $raw = $config['locations'] ?? [];
        $terms = array_values(array_filter(array_map('trim', is_string($raw) ? explode(',', $raw) : (array) $raw)));

        if ($terms === [] || $location === null || trim($location) === '') {
            return true;
        }

        foreach ($terms as $term) {
            if (mb_stripos($location, $term) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function date(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = is_numeric($value)
                // Lever sends milliseconds.
                ? CarbonImmutable::createFromTimestamp((int) ($value > 10_000_000_000 ? $value / 1000 : $value))
                : CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }

        // A posting dated in the future is a feed bug, not a time machine.
        return $date->isFuture() ? CarbonImmutable::now() : $date;
    }
}
