<?php

namespace App\Services\Jobs;

use App\Models\JobSource;
use App\Services\Jobs\Drivers\GreenhouseDriver;
use App\Services\Jobs\Drivers\JobSourceDriver;
use App\Services\Jobs\Drivers\LeverDriver;
use App\Services\Jobs\Drivers\PageDriver;
use App\Services\Jobs\Drivers\RemotiveDriver;
use App\Services\Jobs\Drivers\RssDriver;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/** Reads one source if it is due, and writes down how it went. */
final class JobFetcher
{
    public function __construct(private readonly JobIngestor $ingestor) {}

    public function driver(string $name): JobSourceDriver
    {
        return match ($name) {
            'rss' => app(RssDriver::class),
            'remotive' => app(RemotiveDriver::class),
            'greenhouse' => app(GreenhouseDriver::class),
            'lever' => app(LeverDriver::class),
            'page' => app(PageDriver::class),
            default => throw new InvalidArgumentException("Unknown job source driver [{$name}]."),
        };
    }

    public function isDue(JobSource $source): bool
    {
        if ($source->last_fetched_at === null) {
            return true;
        }

        $asked = (int) ($source->config['interval_minutes'] ?? 60);
        $interval = max($asked, $this->driver($source->driver)->minimumIntervalMinutes());

        // A couple of minutes' slack so an hourly cron does not skip every
        // other run because the last one finished at :00:40.
        return $source->last_fetched_at->lte(now()->subMinutes($interval - 2));
    }

    /**
     * @return array{new: int, merged: int, seen: int}|null null when the source
     *                                                     was skipped or failed
     */
    public function run(JobSource $source, bool $force = false): ?array
    {
        if (! $force && ! $this->isDue($source)) {
            return null;
        }

        try {
            $jobs = $this->driver($source->driver)->fetch($source);
            $counts = $this->ingestor->ingest($source, $jobs);
        } catch (Throwable $e) {
            $source->forceFill([
                'last_fetched_at' => now(),
                'last_status' => 'error',
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();

            Log::warning('JobRadar source failed', ['source' => $source->id, 'error' => $e->getMessage()]);

            return null;
        }

        $source->forceFill([
            'last_fetched_at' => now(),
            'last_status' => 'ok',
            'last_error' => null,
            'last_found_count' => count($jobs),
        ])->save();

        return $counts;
    }
}
