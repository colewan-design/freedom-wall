<?php

namespace App\Console\Commands;

use App\Models\JobSource;
use App\Services\Jobs\JobFetcher;
use App\Services\Jobs\JobIngestor;
use Illuminate\Console\Command;

class FetchJobs extends Command
{
    protected $signature = 'jobs:fetch
        {--source= : Only this source id}
        {--force : Read even if the source is not due yet}';

    protected $description = 'Read every due JobRadar source, merge what is new, and close listings that went away';

    public function handle(JobFetcher $fetcher, JobIngestor $ingestor): int
    {
        $sources = JobSource::query()
            ->active()
            ->when($this->option('source'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        foreach ($sources as $source) {
            $before = $source->last_fetched_at;
            $counts = $fetcher->run($source, (bool) $this->option('force'));

            if ($counts === null) {
                // run() stamps the source even when it fails, so an unchanged
                // stamp is the only sign it was skipped as not due.
                $this->line($source->last_fetched_at == $before
                    ? "{$source->name}: not due"
                    : "<comment>{$source->name}</comment>: {$source->last_error}");

                continue;
            }

            $this->info("{$source->name}: {$counts['new']} new, {$counts['merged']} merged, {$counts['seen']} already known");
        }

        $closed = $ingestor->closeStale();

        if ($closed > 0) {
            $this->line("Closed {$closed} listing(s) past their deadline or gone from every feed.");
        }

        return self::SUCCESS;
    }
}
