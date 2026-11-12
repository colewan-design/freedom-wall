<?php

namespace App\Services\Jobs\Drivers;

use App\Models\JobSource;
use App\Services\Jobs\JobText;
use App\Services\Jobs\NormalizedJob;

/**
 * Remotive's public remote-jobs API.
 *
 * Their terms ask for a link back to the original posting (every listing
 * keeps one) and for no more than a few requests a day, hence the six-hour
 * floor regardless of what the source row asks for.
 *
 * Config: search (optional keyword), category (optional slug), limit.
 */
class RemotiveDriver extends HttpDriver
{
    public function minimumIntervalMinutes(): int
    {
        return 360;
    }

    public function validateConfig(array $config): array
    {
        return [];
    }

    public function fetch(JobSource $source): array
    {
        $config = $source->config;

        $query = array_filter([
            'search' => $config['search'] ?? null,
            'category' => $config['category'] ?? null,
            'limit' => min(100, max(1, (int) ($config['limit'] ?? 50))),
        ]);

        $rows = $this->get('https://remotive.com/api/remote-jobs', $query)->json('jobs') ?? [];
        $jobs = [];

        foreach ($rows as $row) {
            $title = JobText::line($row['title'] ?? null, 200);
            $url = $row['url'] ?? null;

            if ($title === null || ! JobText::isWebUrl($url) || ! isset($row['id'])) {
                continue;
            }

            $where = JobText::line($row['candidate_required_location'] ?? null, 140);

            if (! $this->wantedLocation($where, $config)) {
                continue;
            }

            $jobs[] = new NormalizedJob(
                externalId: (string) $row['id'],
                title: $title,
                url: $url,
                employer: JobText::line($row['company_name'] ?? null, 160),
                location: $where ? "Remote · {$where}" : 'Remote',
                isRemote: true,
                employmentType: JobText::employmentType($row['job_type'] ?? null),
                salaryText: JobText::line($row['salary'] ?? null, 120),
                description: JobText::plain($row['description'] ?? null),
                publishedAt: $this->date($row['publication_date'] ?? null),
            );
        }

        return $jobs;
    }
}
