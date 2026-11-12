<?php

namespace App\Services\Jobs\Drivers;

use App\Models\JobSource;
use App\Services\Jobs\NormalizedJob;

interface JobSourceDriver
{
    /**
     * Read the source once.
     *
     * @return list<NormalizedJob>
     *
     * @throws \RuntimeException when the source cannot be read; the fetcher
     *                           records the message on the source row.
     */
    public function fetch(JobSource $source): array;

    /**
     * The least time between two reads, whatever the source row asks for.
     * Some publishers state a limit in their terms.
     */
    public function minimumIntervalMinutes(): int;

    /**
     * Problems with a config before it is saved, keyed by field.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    public function validateConfig(array $config): array;
}
