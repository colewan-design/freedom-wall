<?php

namespace App\Services\Jobs\Drivers;

use App\Models\JobSource;
use App\Services\Jobs\JobText;
use App\Services\Jobs\NormalizedJob;

/**
 * An employer's Greenhouse job board, through the public Job Board API.
 *
 * Config: board (the token in boards.greenhouse.io/<board>), employer.
 */
class GreenhouseDriver extends HttpDriver
{
    public function validateConfig(array $config): array
    {
        return preg_match('/^[A-Za-z0-9_-]{2,80}$/', (string) ($config['board'] ?? ''))
            ? []
            : ['board' => 'Use the board token from boards.greenhouse.io/<token>.'];
    }

    public function fetch(JobSource $source): array
    {
        $board = $source->config['board'];
        $employer = JobText::line($source->config['employer'] ?? null, 160) ?? $source->name;

        $rows = $this->get("https://boards-api.greenhouse.io/v1/boards/{$board}/jobs", ['content' => 'true'])->json('jobs') ?? [];
        $jobs = [];

        foreach ($rows as $row) {
            $title = JobText::line($row['title'] ?? null, 200);
            $url = $row['absolute_url'] ?? null;

            if ($title === null || ! JobText::isWebUrl($url) || ! isset($row['id'])) {
                continue;
            }

            $location = JobText::line($row['location']['name'] ?? null, 160);

            if (! $this->wantedLocation($location, $source->config)) {
                continue;
            }

            $description = JobText::plain($row['content'] ?? null);

            $jobs[] = new NormalizedJob(
                externalId: (string) $row['id'],
                title: $title,
                url: $url,
                employer: $employer,
                location: $location,
                isRemote: JobText::looksRemote($location, $title),
                employmentType: JobText::employmentType($title, $description),
                description: $description,
                publishedAt: $this->date($row['first_published'] ?? $row['updated_at'] ?? null),
            );
        }

        return $jobs;
    }
}
