<?php

namespace App\Services\Jobs;

use Carbon\CarbonImmutable;

/**
 * One vacancy as a driver read it, before it is matched against what we
 * already have. Every field a source might not publish is nullable, and stays
 * null rather than being guessed — the listing page says "not stated".
 */
final class NormalizedJob
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $title,
        public readonly string $url,
        public readonly ?string $employer = null,
        public readonly ?string $location = null,
        public readonly bool $isRemote = false,
        public readonly ?string $employmentType = null,
        public readonly ?string $salaryText = null,
        public readonly ?string $description = null,
        public readonly ?CarbonImmutable $publishedAt = null,
        public readonly ?CarbonImmutable $deadline = null,
    ) {}
}
