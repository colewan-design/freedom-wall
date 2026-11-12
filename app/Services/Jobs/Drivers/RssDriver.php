<?php

namespace App\Services\Jobs\Drivers;

use App\Models\JobSource;
use App\Services\Jobs\JobText;
use App\Services\Jobs\NormalizedJob;
use Carbon\CarbonImmutable;
use RuntimeException;
use SimpleXMLElement;

/**
 * Any RSS 2.0 or Atom feed of job posts — most job boards, many company
 * career pages, and plenty of government portals publish one.
 *
 * Job boards add their own tags for the fields RSS has no slot for
 * (<job_listing:company>, <himalayasJobs:companyName>, <region>, <type>…).
 * Those are read by local name in any namespace, so a new board usually
 * works without code. Anything the feed does not carry can be set on the
 * source: a fixed employer and location for a single-employer feed.
 *
 * Config:
 *   url                required
 *   employer, location defaults when the feed does not say
 *   employer_in_title  "Company: Job title" style titles (We Work Remotely)
 *   remote             every item is a remote job
 *   keywords           only keep items whose title or summary mentions one
 *   locations          only keep items whose location mentions one
 */
class RssDriver extends HttpDriver
{
    /** Tag names (lowercased, namespace dropped) that carry each field. */
    private const EMPLOYER_TAGS = ['company', 'companyname', 'company_name', 'employer', 'hiringorganization'];

    private const LOCATION_TAGS = ['location', 'locationrestriction', 'region', 'country', 'joblocation', 'job_location'];

    private const TYPE_TAGS = ['type', 'job_type', 'jobtype', 'employmenttype', 'employment_type'];

    private const SALARY_TAGS = ['salary', 'salary_range', 'compensation'];

    private const DEADLINE_TAGS = ['expires_at', 'expirydate', 'expiry_date', 'deadline', 'closingdate', 'validthrough'];

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
            // Say what came back, since "not a feed" is usually a challenge
            // page or an error page served with a 200.
            $start = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? ''), 0, 120);

            throw new RuntimeException('The address did not return a readable RSS or Atom feed'.($start !== '' ? ": “{$start}”" : '.'));
        }

        $isAtom = ! isset($xml->channel);
        $items = $isAtom ? ($xml->entry ?? []) : $xml->channel->item;
        $namespaces = $xml->getDocNamespaces(true);

        $keywords = $this->listOption($config['keywords'] ?? []);
        $jobs = [];

        foreach ($items as $item) {
            $job = $isAtom ? $this->fromAtom($item, $namespaces, $config) : $this->fromRss($item, $namespaces, $config);

            if ($job === null || ! $this->wantedLocation($job->location, $config)) {
                continue;
            }

            if ($keywords !== [] && ! $this->mentionsAny($job, $keywords)) {
                continue;
            }

            $jobs[] = $job;
        }

        return $jobs;
    }

    /**
     * @param  array<string, string>  $namespaces
     * @param  array<string, mixed>  $config
     */
    private function fromRss(SimpleXMLElement $item, array $namespaces, array $config): ?NormalizedJob
    {
        $link = trim((string) $item->link);
        $guid = trim((string) $item->guid) ?: $link;
        $content = isset($namespaces['content']) ? (string) $item->children($namespaces['content'])->encoded : '';
        $summary = $content ?: (string) $item->description;

        return $this->make($guid, (string) $item->title, $link, $summary, (string) $item->pubDate, $this->extras($item, $namespaces), $config);
    }

    /**
     * @param  array<string, string>  $namespaces
     * @param  array<string, mixed>  $config
     */
    private function fromAtom(SimpleXMLElement $entry, array $namespaces, array $config): ?NormalizedJob
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

        return $this->make(trim((string) $entry->id) ?: $link, (string) $entry->title, $link, $summary, $date, $this->extras($entry, $namespaces), $config);
    }

    /**
     * Every child element, in every namespace, grouped by lowercased local
     * name. Repeated tags (Himalayas lists one locationRestriction per
     * country) keep all their values.
     *
     * @param  array<string, string>  $namespaces
     * @return array<string, list<string>>
     */
    private function extras(SimpleXMLElement $item, array $namespaces): array
    {
        $found = [];

        foreach ([null, ...array_values($namespaces)] as $uri) {
            foreach ($item->children($uri) as $name => $child) {
                $value = trim((string) $child);

                if ($value !== '') {
                    $found[strtolower($name)][] = $value;
                }
            }
        }

        return $found;
    }

    /**
     * @param  array<string, list<string>>  $extras
     * @param  array<string, mixed>  $config
     */
    private function make(string $id, string $title, string $link, string $summary, string $date, array $extras, array $config): ?NormalizedJob
    {
        $title = JobText::line($title, 300);

        if ($title === null || ! JobText::isWebUrl($link)) {
            return null;
        }

        $employer = $this->first($extras, self::EMPLOYER_TAGS);

        if (! empty($config['employer_in_title']) && preg_match('/^(.{2,80}?):\s+(.+)$/u', $title, $m)) {
            $employer ??= $m[1];
            $title = $m[2];
        }

        $location = $this->all($extras, self::LOCATION_TAGS) ?? JobText::line($config['location'] ?? null, 160);
        $remote = ! empty($config['remote']);
        $description = JobText::plain($summary);
        $type = $this->first($extras, self::TYPE_TAGS);

        return new NormalizedJob(
            externalId: mb_substr($id, 0, 191),
            title: mb_substr($title, 0, 200),
            url: $link,
            employer: JobText::line($employer ?? ($config['employer'] ?? null), 160),
            location: JobText::line($remote ? trim('Remote · '.$location, ' ·') : $location, 160),
            isRemote: $remote || JobText::looksRemote($title, $location),
            employmentType: JobText::employmentType($type, $title, $type ? null : $description),
            salaryText: JobText::line($this->first($extras, self::SALARY_TAGS), 120),
            description: $description,
            publishedAt: $this->date($date),
            deadline: $this->deadline($this->first($extras, self::DEADLINE_TAGS)),
        );
    }

    /**
     * @param  array<string, list<string>>  $extras
     * @param  list<string>  $names
     */
    private function first(array $extras, array $names): ?string
    {
        foreach ($names as $name) {
            if (isset($extras[$name][0])) {
                return $extras[$name][0];
            }
        }

        return null;
    }

    /**
     * Every value under any of these names, joined — "Asia Only" and the
     * country list are both location, and both worth keeping.
     *
     * @param  array<string, list<string>>  $extras
     * @param  list<string>  $names
     */
    private function all(array $extras, array $names): ?string
    {
        $values = [];

        foreach ($names as $name) {
            foreach ($extras[$name] ?? [] as $value) {
                $values[mb_strtolower($value)] = $value;
            }
        }

        return $values === [] ? null : implode(', ', $values);
    }

    private function deadline(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<string> */
    private function listOption(mixed $value): array
    {
        $items = is_string($value) ? explode(',', $value) : (array) $value;

        return array_values(array_filter(array_map('trim', $items)));
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
