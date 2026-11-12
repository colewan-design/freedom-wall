<?php

namespace App\Services\Jobs\Drivers;

use App\Models\JobSource;
use App\Services\Jobs\JobText;
use App\Services\Jobs\NormalizedJob;
use RuntimeException;
use SimpleXMLElement;

/**
 * Any RSS 2.0 or Atom feed of job posts — most job boards, many company
 * career pages, and plenty of government portals publish one.
 *
 * Feeds rarely carry structured fields, so the config can name the employer
 * and a default location for a single-employer feed, and everything else is
 * read out of the title and summary where it is stated.
 *
 * Config: url (required), employer, location, keywords (only keep items whose
 * title or summary mentions one of these — for broad feeds).
 */
class RssDriver extends HttpDriver
{
    public function validateConfig(array $config): array
    {
        return JobText::isWebUrl($config['url'] ?? null) ? [] : ['url' => 'Give the full http(s) address of the feed.'];
    }

    public function fetch(JobSource $source): array
    {
        $config = $source->config;
        $body = $this->get($config['url'], [], 'application/rss+xml, application/atom+xml, application/xml, text/xml')->body();

        $previous = libxml_use_internal_errors(true);

        try {
            // LIBXML_NONET: a feed has no business making us fetch anything else.
            $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($xml === false) {
            throw new RuntimeException('The address did not return a readable RSS or Atom feed.');
        }

        $items = isset($xml->channel->item) ? $xml->channel->item : ($xml->entry ?? []);
        $isAtom = ! isset($xml->channel);

        $keywords = array_values(array_filter(array_map('trim', (array) ($config['keywords'] ?? []))));
        $jobs = [];

        foreach ($items as $item) {
            $job = $isAtom ? $this->fromAtom($item, $config) : $this->fromRss($item, $config);

            if ($job === null) {
                continue;
            }

            if ($keywords !== [] && ! $this->mentionsAny($job, $keywords)) {
                continue;
            }

            $jobs[] = $job;
        }

        return $jobs;
    }

    /** @param array<string, mixed> $config */
    private function fromRss(SimpleXMLElement $item, array $config): ?NormalizedJob
    {
        $link = trim((string) $item->link);
        $guid = trim((string) $item->guid) ?: $link;
        $summary = (string) ($item->children('content', true)->encoded ?? '') ?: (string) $item->description;

        return $this->make($guid, (string) $item->title, $link, $summary, (string) $item->pubDate, $config);
    }

    /** @param array<string, mixed> $config */
    private function fromAtom(SimpleXMLElement $entry, array $config): ?NormalizedJob
    {
        $link = '';

        foreach ($entry->link as $candidate) {
            $rel = (string) $candidate['rel'];

            if ($rel === '' || $rel === 'alternate') {
                $link = (string) $candidate['href'];
                break;
            }
        }

        $summary = (string) $entry->content ?: (string) $entry->summary;
        $date = (string) $entry->published ?: (string) $entry->updated;

        return $this->make(trim((string) $entry->id) ?: $link, (string) $entry->title, $link, $summary, $date, $config);
    }

    /** @param array<string, mixed> $config */
    private function make(string $id, string $title, string $link, string $summary, string $date, array $config): ?NormalizedJob
    {
        $title = JobText::line($title, 200);

        if ($title === null || ! JobText::isWebUrl($link)) {
            return null;
        }

        $description = JobText::plain($summary);

        return new NormalizedJob(
            externalId: mb_substr($id, 0, 191),
            title: $title,
            url: $link,
            employer: JobText::line($config['employer'] ?? null, 160),
            location: JobText::line($config['location'] ?? null, 160),
            isRemote: JobText::looksRemote($title, $description),
            employmentType: JobText::employmentType($title, $description),
            description: $description,
            publishedAt: $this->date($date),
        );
    }

    /** @param list<string> $keywords */
    private function mentionsAny(NormalizedJob $job, array $keywords): bool
    {
        $text = mb_strtolower($job->title.' '.$job->description);

        foreach ($keywords as $keyword) {
            if (str_contains($text, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }
}
