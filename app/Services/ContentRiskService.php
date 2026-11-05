<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Reads a submission and says how badly it wants a human's attention first.
 *
 * This is deliberately NOT a filter. Nothing here rejects anything — every
 * submission still lands in the same pending queue, and a person still decides.
 * All a score does is reorder that queue, so the post that names a classmate
 * and prints her number gets read before the one about the canteen rice.
 *
 * It is rules, not a model, which buys precision and costs recall. It catches
 * the blatant cases and misses the clever ones: sarcasm, in-jokes and implied
 * threats all read as clean here. That is survivable precisely because missing
 * one only means the post is read in its normal turn — which is what happens to
 * every post today anyway. See moderator.md for what the tiers above this do.
 *
 * Three categories, each argued for in config/moderation.php:
 *
 *   contact_details  a phone number, email, profile link or student number.
 *                    Almost never a false positive, and a Facebook Privacy
 *                    Violation whoever the post is about.
 *   named_target     the post points at one identifiable person AND says
 *                    something unkind near them. Neither half counts alone.
 *   self_harm        the poster sounds like they are in trouble. Gets a
 *                    helpline in front of them; never blocks the post.
 */
class ContentRiskService
{
    /**
     * Patterns for things that identify a person off this wall. Run against the
     * raw text — none of them care about casing, and all of them are specific
     * enough not to need the evasion handling ContentFilterService does.
     */
    private const CONTACT_PATTERNS = [
        // PH mobile, written any of the ways people write it: 09171234567,
        // 0917 123 4567, 0917-123-4567, +63 917 123 4567.
        '/(?<![\d])(?:\+?63[\s.-]?|0)9\d{2}[\s.-]?\d{3}[\s.-]?\d{4}(?![\d])/',

        '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i',

        '#(?:facebook\.com|fb\.com|fb\.me|m\.me|instagram\.com|tiktok\.com)/[a-z0-9._-]{3,}#i',

        // Student numbers: a year, then the serial. "2023-01234", "21-04567".
        // The inner lookahead keeps school years out of it — "SY 2024-2025" is
        // on half the announcement posts this wall gets.
        '/(?<!\d)(?:19|20)?\d{2}-(?!(?:19|20)\d{2}(?!\d))\d{4,6}(?!\d)/',
    ];

    /**
     * Tagalog marks a personal name with a particle in front of it, which makes
     * "si Maria" a far better name detector than any capitalisation rule — and
     * it is the form a call-out post almost always takes.
     *
     * Only the particles that mark a *person*. "yung" and "nung" point at
     * anything at all ("yung Calculus exam"), so they are not here.
     */
    private const NAME_PARTICLES = ['si', 'ni', 'kay', 'kina', 'nina', 'sina'];

    /**
     * Somebody else's contact details are a privacy violation. Your own are a
     * classified ad, and this wall runs on them. These mark the difference.
     */
    private const OTHER_PERSON_MARKERS = [
        'niya', 'nila', 'kanya', 'kanya', 'kanila', 'sakanya', 'her', 'hers',
        'his', 'their', 'theirs', 'she', 'he',
    ];

    /**
     * How far from a name an insult has to be before it stops being about that
     * name, in words. Wall posts are short; this is roughly a clause either way.
     */
    private const PROXIMITY_WORDS = 8;

    /**
     * Endings that leave a negative term the same word: "bobo" -> "bobong",
     * "nakakahiya" -> "nakakahiyang". Kept short for the same reason
     * ContentFilterService keeps its own list short — every ending added here
     * widens the net over innocent words too.
     */
    private const SUFFIXES = ['', 's', 'ng', 'an', 'in', 'han', 'hin'];

    private bool $enabled;

    /** @var array<string, int> */
    private array $weights;

    /** @var list<string> */
    private array $negativeTerms;

    /** @var list<string> */
    private array $selfHarmPhrases;

    /** @var list<string> */
    private array $courseCodes;

    /** @var list<string> */
    private array $nameAllowlist;

    /** @var list<string> */
    private array $selfContactMarkers;

    public function __construct(?bool $enabled = null)
    {
        $this->enabled = $enabled ?? (bool) config('moderation.enabled', true);
        $this->weights = (array) config('moderation.weights', []);
        $this->negativeTerms = $this->lowered((array) config('moderation.negative_terms', []));
        $this->selfHarmPhrases = $this->lowered((array) config('moderation.self_harm_phrases', []));
        $this->courseCodes = $this->lowered((array) config('moderation.course_codes', []));
        $this->nameAllowlist = $this->lowered((array) config('moderation.name_allowlist', []));
        $this->selfContactMarkers = $this->lowered((array) config('moderation.self_contact_markers', []));
    }

    public function assess(string $text): ContentRisk
    {
        if (! $this->enabled || trim($text) === '') {
            return ContentRisk::none();
        }

        $tokens = $this->tokenise($text);

        $flags = array_filter([
            'self_harm' => $this->selfHarm($tokens),
            'contact_details' => $this->contactDetails($text, $tokens),
            'named_target' => $this->namedTarget($tokens),
        ]);

        if ($flags === []) {
            return ContentRisk::none();
        }

        $score = 0;
        foreach (array_keys($flags) as $category) {
            $score += (int) ($this->weights[$category] ?? 0);
        }

        return new ContentRisk(min(100, $score), $flags);
    }

    /**
     * Phone numbers, emails, profile links and student numbers, as written.
     *
     * Skips the post that is plainly handing out its own number. Half this
     * wall is selling a calculator or looking for a tutor, and flagging all of
     * it would bury the queue in exactly the posts nobody needs to read first.
     * A post that mentions somebody *else* alongside the number keeps the flag.
     *
     * @param  list<array{word: string, lower: string}>  $tokens
     * @return list<string>
     */
    private function contactDetails(string $text, array $tokens): array
    {
        if ($this->sharesOwnContact($tokens) && ! $this->mentionsAnotherPerson($tokens)) {
            return [];
        }

        $found = [];

        foreach (self::CONTACT_PATTERNS as $pattern) {
            if (preg_match_all($pattern, $text, $matches) === 0) {
                continue;
            }

            foreach ($matches[0] as $match) {
                $found[trim($match)] = true;
            }
        }

        return array_keys($found);
    }

    /**
     * Whether the post reads as the author handing out their own details.
     *
     * @param  list<array{word: string, lower: string}>  $tokens
     */
    private function sharesOwnContact(array $tokens): bool
    {
        $haystack = ' '.implode(' ', array_column($tokens, 'lower')).' ';

        foreach ($this->selfContactMarkers as $marker) {
            if (str_contains($haystack, ' '.$marker.' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{word: string, lower: string}>  $tokens
     */
    private function mentionsAnotherPerson(array $tokens): bool
    {
        foreach ($tokens as $index => $token) {
            if (in_array($token['lower'], self::OTHER_PERSON_MARKERS, true)) {
                return true;
            }

            if (in_array($token['lower'], self::NAME_PARTICLES, true)
                && isset($tokens[$index + 1])
                && $this->looksLikeName($tokens[$index + 1]['word'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Someone identifiable, with something unkind said near them.
     *
     * The two halves are separate on purpose. A post full of insults and no
     * target is someone venting, which is what the wall is for. A post naming
     * a person with nothing bad attached is a birthday greeting. It is the
     * pair that is worth reading early.
     *
     * @param  list<array{word: string, lower: string}>  $tokens
     * @return list<string>
     */
    private function namedTarget(array $tokens): array
    {
        $negatives = $this->negativeMatches($tokens);

        if ($negatives === []) {
            return [];
        }

        $found = [];

        foreach ($this->identifiers($tokens) as [$identifier, $index, $needsProximity]) {
            if ($needsProximity && ! $this->nearAny($index, array_keys($negatives))) {
                continue;
            }

            $found[$identifier] = true;
        }

        if ($found === []) {
            return [];
        }

        // The insult travels with the name: an admin skimming the queue needs
        // both halves to judge the flag without reopening the post.
        return array_values(array_unique([...array_keys($found), ...array_values($negatives)]));
    }

    /**
     * Every way this text points at a particular person, as
     * [identifier, token index, whether an insult has to be nearby].
     *
     * Particles, handles and section codes are specific enough to count from
     * anywhere in a short post. A bare capitalised name is not — half the wall
     * writes "Happy Birthday" — so that one only counts next to an insult.
     *
     * @param  list<array{word: string, lower: string}>  $tokens
     * @return list<array{0: string, 1: int, 2: bool}>
     */
    private function identifiers(array $tokens): array
    {
        $found = [];
        $count = count($tokens);

        foreach ($tokens as $index => $token) {
            // "si Maria", "kay Jhen"
            if (in_array($token['lower'], self::NAME_PARTICLES, true)
                && $index + 1 < $count
                && $this->looksLikeName($tokens[$index + 1]['word'])) {
                $found[] = [$token['lower'].' '.$tokens[$index + 1]['word'], $index, false];

                continue;
            }

            // "@jhenchan"
            if (preg_match('/^@[a-z][a-z0-9._]{2,}$/i', $token['word']) === 1) {
                $found[] = [$token['word'], $index, false];

                continue;
            }

            // "BSIT 2-A", "bsit2a", or a bare "3-B" next to a course word.
            if ($section = $this->sectionCode($tokens, $index)) {
                $found[] = [$section, $index, false];

                continue;
            }

            // "Maria Santos"
            if ($this->looksLikeName($token['word'])
                && $index + 1 < $count
                && $this->looksLikeName($tokens[$index + 1]['word'])) {
                $pair = $token['lower'].' '.$tokens[$index + 1]['lower'];

                if (! in_array($pair, $this->nameAllowlist, true)) {
                    $found[] = [$token['word'].' '.$tokens[$index + 1]['word'], $index, true];
                }
            }
        }

        return $found;
    }

    /**
     * A programme and section, which narrows a post to about forty people.
     * Matches "BSIT 2-A", "BSIT-2A" and "bsit 2a" alike.
     *
     * @param  list<array{word: string, lower: string}>  $tokens
     */
    private function sectionCode(array $tokens, int $index): ?string
    {
        $word = $tokens[$index]['lower'];

        foreach ($this->courseCodes as $code) {
            if (! str_starts_with($word, $code)) {
                continue;
            }

            $tail = substr($word, strlen($code));

            // Section rode along in the same token: "bsit-2a", "bsit2a".
            if (preg_match('/^-?\d-?[a-z]$/', $tail) === 1) {
                return $tokens[$index]['word'];
            }

            // Section is the next token: "bsit 2-a", "bsit 2a".
            if ($tail === '' && isset($tokens[$index + 1])
                && preg_match('/^\d-?[a-z]$/', $tokens[$index + 1]['lower']) === 1) {
                return $tokens[$index]['word'].' '.$tokens[$index + 1]['word'];
            }
        }

        return null;
    }

    /**
     * Negative terms present, as token index => term. Indexes are what the
     * proximity check works in — words, not bytes, so accents and casing
     * cannot knock the arithmetic out.
     *
     * @param  list<array{word: string, lower: string}>  $tokens
     * @return array<int, string>
     */
    private function negativeMatches(array $tokens): array
    {
        $lowered = array_column($tokens, 'lower');
        $found = [];

        foreach ($this->negativeTerms as $term) {
            $words = explode(' ', $term);
            $length = count($words);
            $last = count($lowered) - $length;

            for ($i = 0; $i <= $last; $i++) {
                if ($this->matchesAt($lowered, $i, $words)) {
                    $found[$i] = $term;
                }
            }
        }

        return $found;
    }

    /**
     * Whether a phrase sits at this token index. Every word but the last has to
     * match outright; the last may carry one of the endings in SUFFIXES, so
     * "bobo" finds "bobong" without the config listing both.
     *
     * @param  list<string>  $lowered
     * @param  list<string>  $words
     */
    private function matchesAt(array $lowered, int $index, array $words): bool
    {
        $last = count($words) - 1;

        foreach ($words as $offset => $word) {
            $token = $lowered[$index + $offset];

            if ($offset < $last) {
                if ($token !== $word) {
                    return false;
                }

                continue;
            }

            if (! str_starts_with($token, $word)) {
                return false;
            }

            if (! in_array(substr($token, strlen($word)), self::SUFFIXES, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<int>  $indexes
     */
    private function nearAny(int $index, array $indexes): bool
    {
        foreach ($indexes as $other) {
            if (abs($other - $index) <= self::PROXIMITY_WORDS) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{word: string, lower: string}>  $tokens
     * @return list<string>
     */
    private function selfHarm(array $tokens): array
    {
        if ($tokens === []) {
            return [];
        }

        $haystack = ' '.implode(' ', array_column($tokens, 'lower')).' ';
        $found = [];

        foreach ($this->selfHarmPhrases as $phrase) {
            if (str_contains($haystack, ' '.$phrase.' ')) {
                $found[] = $phrase;
            }
        }

        return $found;
    }

    /**
     * A capitalised word that could be somebody's first name. Deliberately
     * strict: one capital, the rest lower, at least three letters. "OK", "BSU"
     * and "SHOUTOUT" are not names, and neither is "ang".
     */
    private function looksLikeName(string $word): bool
    {
        return preg_match('/^\p{Lu}\p{Ll}{2,}$/u', $word) === 1;
    }

    /**
     * Split into words, keeping each one twice: as written, because capitals
     * are half of how a name is spotted, and folded down to lowercase ASCII,
     * because that is what the term lists are written in.
     *
     * @return list<array{word: string, lower: string}>
     */
    private function tokenise(string $text): array
    {
        if (preg_match_all('/\S+/u', $text, $matches) === 0) {
            return [];
        }

        $tokens = [];

        foreach ($matches[0] as $raw) {
            // Trim the punctuation around a word but keep what sits inside one:
            // "Maria," is a name, "can't" is one word, "@jhen" keeps its @.
            $word = preg_replace('/^[^\p{L}\p{N}@]+|[^\p{L}\p{N}]+$/u', '', $raw) ?? $raw;

            if ($word === '') {
                continue;
            }

            $tokens[] = ['word' => $word, 'lower' => Str::lower(Str::ascii($word))];
        }

        return $tokens;
    }

    /**
     * @param  array<int, string>  $values
     * @return list<string>
     */
    private function lowered(array $values): array
    {
        return array_values(array_filter(array_map(
            fn (string $value): string => Str::lower(Str::ascii(trim($value))),
            $values,
        )));
    }
}
