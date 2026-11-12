<?php

namespace App\Services\Jobs\Drivers;

use App\Models\JobSource;
use App\Services\Jobs\JobText;
use App\Services\Jobs\NormalizedJob;

/**
 * An employer's Lever postings, through the public Postings API.
 *
 * Config: company (the slug in jobs.lever.co/<company>), employer.
 */
class LeverDriver extends HttpDriver
{
    public function validateConfig(array $config): array
    {
        return preg_match('/^[A-Za-z0-9_-]{2,80}$/', (string) ($config['company'] ?? ''))
            ? []
            : ['company' => 'Use the company slug from jobs.lever.co/<company>.'];
    }

    public function fetch(JobSource $source): array
    {
        $company = $source->config['company'];
        $employer = JobText::line($source->config['employer'] ?? null, 160) ?? $source->name;

        $rows = $this->get("https://api.lever.co/v0/postings/{$company}", ['mode' => 'json'])->json() ?? [];
        $jobs = [];

        foreach ($rows as $row) {
            $title = JobText::line($row['text'] ?? null, 200);
            $url = $row['hostedUrl'] ?? null;

            if ($title === null || ! JobText::isWebUrl($url) || ! isset($row['id'])) {
                continue;
            }

            $location = JobText::line($row['categories']['location'] ?? null, 160);

            if (! $this->wantedLocation($location, $source->config)) {
                continue;
            }

            $description = JobText::plain($row['descriptionPlain'] ?? $row['description'] ?? null);

            $jobs[] = new NormalizedJob(
                externalId: (string) $row['id'],
                title: $title,
                url: $url,
                employer: $employer,
                location: $location,
                isRemote: ($row['workplaceType'] ?? null) === 'remote' || JobText::looksRemote($location),
                employmentType: JobText::employmentType($row['categories']['commitment'] ?? null, $title),
                salaryText: $this->salary($row['salaryRange'] ?? null),
                description: $description,
                publishedAt: $this->date($row['createdAt'] ?? null),
            );
        }

        return $jobs;
    }

    /** @param array<string, mixed>|null $range */
    private function salary(?array $range): ?string
    {
        if (! $range || ! isset($range['min'], $range['max'])) {
            return null;
        }

        $currency = $range['currency'] ?? '';
        $interval = isset($range['interval']) ? ' '.str_replace('-', ' ', $range['interval']) : '';

        return trim(sprintf('%s %s–%s%s', $currency, number_format((float) $range['min']), number_format((float) $range['max']), $interval));
    }
}
