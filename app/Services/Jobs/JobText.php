<?php

namespace App\Services\Jobs;

use App\Models\JobListing;
use Illuminate\Support\Str;

/**
 * Text helpers shared by the drivers, the community form and the matcher, so
 * "Full Time", "full_time" and "FT" all land on the same stored value.
 */
final class JobText
{
    /** Feed HTML down to plain paragraphs. Nothing from a feed renders as HTML. */
    public static function plain(?string $html, int $limit = JobListing::MAX_DESCRIPTION_LENGTH): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        // Some feeds double-escape (Greenhouse sends &lt;p&gt;), so decode
        // before stripping and again after.
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('#<\s*(br|/p|/li|/h[1-6]|/div)\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#<\s*li[^>]*>#i', "\n• ", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n\s*\n\s*(\n\s*)+/", "\n\n", $text) ?? $text;
        $text = trim(preg_replace("/ *\n */", "\n", $text) ?? $text);

        return $text === '' ? null : self::fit($text, $limit);
    }

    public static function line(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');

        return $value === '' ? null : self::fit($value, $limit);
    }

    /**
     * Cut to at most $limit characters, ellipsis included. Str::limit counts
     * display width and then appends the ellipsis, so its result can run past
     * a varchar column — a 30-flag location list from one feed did exactly
     * that. Character count is what MySQL measures, so count characters.
     */
    public static function fit(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit - 1), " ,·;").'…';
    }

    /** Map whatever a source calls the arrangement onto our five values. */
    public static function employmentType(?string ...$hints): ?string
    {
        $haystack = Str::lower(implode(' ', array_filter($hints)));

        if ($haystack === '') {
            return null;
        }

        return match (true) {
            (bool) preg_match('/\bintern(ship)?\b|\bojt\b|on[- ]the[- ]job training/', $haystack) => 'internship',
            (bool) preg_match('/part[-_ ]?time/', $haystack) => 'part-time',
            (bool) preg_match('/\bfreelance|\bgig\b|project[- ]based/', $haystack) => 'freelance',
            (bool) preg_match('/contract|contractual|\bcos\b|contract of service|job order|\btemporary\b|casual/', $haystack) => 'contractual',
            (bool) preg_match('/full[-_ ]?time|\bpermanent\b|\bregular\b|plantilla/', $haystack) => 'full-time',
            default => null,
        };
    }

    public static function looksRemote(?string ...$hints): bool
    {
        return (bool) preg_match('/\bremote\b|work from home|\bwfh\b|anywhere/i', implode(' ', array_filter($hints)));
    }

    /**
     * Government only when the posting says so in a way that is hard to fake
     * by accident: a .gov.ph link or an agency name. Everything else is left
     * unknown rather than assumed private.
     */
    public static function sector(?string $employer, ?string $url): ?string
    {
        $host = Str::lower((string) parse_url((string) $url, PHP_URL_HOST));

        if (str_ends_with($host, '.gov.ph') || str_ends_with($host, '.gov')) {
            return 'government';
        }

        if ($employer && preg_match('/\b(department of|city government|municipal(ity)? of|provincial government|deped|dost|dict|dole|civil service commission|lgu|barangay|state university|national government)\b/i', $employer)) {
            return 'government';
        }

        return null;
    }

    /** Lowercase words, no punctuation, no filler — the comparable core. */
    public static function normalize(?string $value): string
    {
        $value = Str::lower(Str::ascii((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
        $value = preg_replace('/\b(inc|corp|corporation|co|ltd|llc|the|and|of|ph|philippines)\b/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    public static function isWebUrl(?string $url): bool
    {
        return is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(Str::lower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
