<?php

namespace App\Services\Jobs\Drivers;

use App\Models\JobSource;
use App\Services\Jobs\JobText;
use App\Services\Jobs\NormalizedJob;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * A plain web page that lists vacancies as links — the usual shape of an
 * agency or LGU "job openings" page that publishes no feed.
 *
 * Each link whose address contains `link_contains` is one job: its text is
 * the title and its address the listing. The row or card around the link is
 * read for what Philippine government postings usually state (salary as
 * "P 38,764.00 monthly", "Apply by November 20, 2026", CASUAL / PERMANENT /
 * COS), and kept as the description.
 *
 * Polite by construction: robots.txt is honoured, a page is read at most
 * every six hours, and a site that answers with a block or a challenge is
 * left alone — the error is recorded, never worked around.
 *
 * Config: url, link_contains (both required), employer, location.
 */
class PageDriver extends HttpDriver
{
    public function minimumIntervalMinutes(): int
    {
        return 360;
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (! JobText::isWebUrl($config['url'] ?? null)) {
            $errors['url'] = 'Give the full http(s) address of the page that lists the openings.';
        }

        if (blank($config['link_contains'] ?? null)) {
            $errors['link_contains'] = 'Say what every job link has in its address, e.g. /jobs/.';
        }

        return $errors;
    }

    public function fetch(JobSource $source): array
    {
        $config = $source->config;
        $url = $config['url'];

        if (! $this->robotsAllow($url)) {
            throw new RuntimeException('robots.txt on this site does not allow reading this page.');
        }

        $html = $this->get($url, [], 'text/html,application/xhtml+xml')->body();

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $needle = (string) $config['link_contains'];
        $excludes = array_values(array_filter(array_map('trim', is_string($config['link_excludes'] ?? null)
            ? explode(',', $config['link_excludes'])
            : (array) ($config['link_excludes'] ?? []))));

        // Every job link first, so each one's block can be told apart from
        // its neighbours' below.
        $links = [];

        foreach ((new DOMXPath($dom))->query('//a[@href]') as $anchor) {
            /** @var DOMElement $anchor */
            $href = $this->absolute(trim($anchor->getAttribute('href')), $url);

            if ($href === null || ! str_contains($href, $needle) || rtrim($href, '/') === rtrim($url, '/')) {
                continue;
            }

            foreach ($excludes as $exclude) {
                if (str_contains($href, $exclude)) {
                    continue 2;
                }
            }

            $title = JobText::line($anchor->textContent, 200);

            // Navigation and "How to apply" links share the path; a job
            // title is more than a word or two of chrome.
            if ($title === null || Str::length($title) < 3 || preg_match('/^(how to apply|apply( now)?|view|details|read more|more|job openings?|vacancies|careers?)$/i', $title)) {
                continue;
            }

            $links[] = [$anchor, $href, $title];
        }

        $jobAnchors = array_map(fn ($link) => $link[0], $links);
        $perHref = array_count_values(array_map(fn ($link) => $link[1], $links));
        $jobs = [];
        $seen = [];

        foreach ($links as [$anchor, $href, $title]) {
            $context = $this->contextOf($anchor, $title, $jobAnchors);
            $title = $this->withDetail($title, $context);

            // Usually the link is the identity. A page that points several
            // openings at one notice (BSU links three Instructor posts to the
            // same PDF) keeps them apart by what makes each one different.
            $key = $perHref[$href] > 1 ? $href.'#'.Str::slug($title) : $href;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $jobs[] = new NormalizedJob(
                externalId: mb_substr($key, 0, 191),
                title: $title,
                url: $href,
                employer: JobText::line($config['employer'] ?? null, 160),
                location: JobText::line($config['location'] ?? null, 160),
                isRemote: JobText::looksRemote($title, $context),
                employmentType: JobText::employmentType($context, $title),
                salaryText: $this->salary($context),
                description: $context !== '' ? $context : null,
                deadline: $this->deadline($context),
            );
        }

        if ($jobs === []) {
            throw new RuntimeException("No links containing \"{$needle}\" were found on the page. The site may have changed its layout.");
        }

        return $jobs;
    }

    /**
     * The text of the block the link belongs to, minus the title itself and
     * countdowns that would go stale ("6 days left").
     *
     * The block is the largest ancestor that holds no other job link: a list
     * row on an HRIS board, a paragraph in a WordPress post. Stopping one
     * level short of the first ancestor that does is what keeps one opening's
     * salary and deadline from being read off its neighbour.
     *
     * @param  list<DOMElement>  $jobAnchors
     */
    private function contextOf(DOMElement $anchor, string $title, array $jobAnchors): string
    {
        $card = null;
        $node = $anchor->parentNode;

        for ($depth = 0; $node instanceof DOMElement && $depth < 8; $depth++, $node = $node->parentNode) {
            if ($this->holdsAnotherJob($node, $anchor, $jobAnchors)) {
                break;
            }

            $card = $node;
        }

        if (! $card instanceof DOMElement) {
            return '';
        }

        $parts = [];

        foreach ((new DOMXPath($card->ownerDocument))->query('.//text()', $card) as $text) {
            $value = JobText::line($text->textContent, 300);

            if ($value !== null) {
                $parts[] = $value;
            }
        }

        $text = implode(' · ', $parts);
        $text = str_replace(JobText::line($title, 300) ?? '', '', $text);
        $text = preg_replace('/\b\d+\s+days?\s+left\b|\b(today|tomorrow) is the last day\b/i', '', $text) ?? $text;
        $text = trim(preg_replace('/(\s*·\s*)+/', ' · ', $text) ?? $text, " ·");

        return Str::limit($text, 600, '…');
    }

    /** @param list<DOMElement> $jobAnchors */
    private function holdsAnotherJob(DOMElement $node, DOMElement $anchor, array $jobAnchors): bool
    {
        foreach ($node->getElementsByTagName('a') as $inner) {
            if ($inner !== $anchor && in_array($inner, $jobAnchors, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Government notices repeat one title across offices ("Instructor 1" for
     * three colleges, "Administrative Aide IV" for two offices). The
     * specialization, or failing that the place of assignment, is what tells
     * them apart — and what someone scanning the board needs to see anyway.
     */
    private function withDetail(string $title, string $context): string
    {
        foreach (['specialization', 'place of assignment', 'assignment', 'office'] as $label) {
            if (preg_match('/\b'.$label.'\s*:\s*([^·]+?)\s*(?:·|$)/iu', $context, $m)) {
                $detail = JobText::line($m[1], 100);

                if ($detail !== null && ! str_contains(mb_strtolower($title), mb_strtolower($detail))) {
                    return JobText::fit("{$title} — {$detail}", 200);
                }
            }
        }

        return $title;
    }

    private function salary(string $context): ?string
    {
        if (! preg_match('/(?:₱|PHP|Php|P)\s?\d[\d,]*(?:\.\d+)?(?:\s*\(SG\s*\d+\))?\s*(?:\/\s*)?(?:daily|monthly|per\s+(?:day|month)|a\s+(?:day|month)|day|month)?/u', $context, $m)) {
            return null;
        }

        $salary = trim($m[0]);

        // "Monthly Salary: Php 33,947.00 (SG 12)" puts the period in the label.
        if (! preg_match('/daily|monthly|day|month/i', $salary) && preg_match('/\b(monthly|daily)\s+(salary|rate|wage)\b/i', $context, $period)) {
            $salary .= ' '.strtolower($period[1]);
        }

        return JobText::line($salary, 120);
    }

    private function deadline(string $context): ?CarbonImmutable
    {
        $months = 'January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec';

        if (preg_match("/(?:apply by|deadline|until|on or before|closing date|not later than)[:\s]*((?:{$months})\.? \d{1,2},? \d{4})/i", $context, $m)) {
            try {
                return CarbonImmutable::parse(str_replace('Sept', 'Sep', $m[1]));
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function absolute(string $href, string $base): ?string
    {
        if ($href === '' || str_starts_with($href, '#') || preg_match('/^(mailto|tel|javascript):/i', $href)) {
            return null;
        }

        if (JobText::isWebUrl($href)) {
            return $href;
        }

        $parts = parse_url($base);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($href, '//')) {
            return $parts['scheme'].':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $dir = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/') ?: '/';

        return $origin.$dir.$href;
    }

    /**
     * A minimal robots.txt check: the rules for `*` (and for us by name),
     * Disallow prefixes only. Cached for a day so a fetch is not two requests.
     */
    private function robotsAllow(string $url): bool
    {
        $parts = parse_url($url);
        $origin = $parts['scheme'].'://'.$parts['host'];
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        $rules = Cache::remember('jobradar:robots:'.md5($origin), now()->addDay(), function () use ($origin) {
            try {
                $response = $this->get($origin.'/robots.txt', [], 'text/plain');
            } catch (Throwable) {
                return []; // No robots.txt (or unreadable): nothing is disallowed.
            }

            $disallow = [];
            $applies = false;

            foreach (preg_split('/\r?\n/', $response->body()) as $line) {
                $line = trim(preg_replace('/#.*$/', '', $line) ?? '');

                if (preg_match('/^user-agent:\s*(.+)$/i', $line, $m)) {
                    $agent = strtolower(trim($m[1]));
                    $applies = $agent === '*' || str_contains('bsufreedomwall-jobradar', $agent);
                } elseif ($applies && preg_match('/^disallow:\s*(\S+)/i', $line, $m)) {
                    $disallow[] = $m[1];
                }
            }

            return $disallow;
        });

        foreach ($rules as $prefix) {
            if ($prefix === '/' || str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }
}
