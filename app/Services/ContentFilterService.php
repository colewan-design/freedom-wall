<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Rejects profanity and trash talk before it reaches the wall, the feed, or the
 * global chat. This does NOT replace human review — wall submissions still go
 * through the admin queue — it only stops the obvious cases from being stored.
 *
 * The term list lives in config/profanity.php and is meant to be edited there.
 * Everything in this class is about catching the ways people write around such
 * a list: casing, accents, lookalike characters, stretched letters, masked
 * letters, and letters spaced apart.
 */
class ContentFilterService
{
    /** Characters that stand in for a letter: "p0ta", "@sshole", "fvck". */
    private const LOOKALIKES = [
        'a' => 'a@4', 'b' => 'b8', 'c' => 'c(', 'e' => 'e3', 'g' => 'g9',
        'i' => 'i1!|', 'l' => 'l1|', 'o' => 'o0', 's' => 's5$', 't' => 't7',
        'u' => 'uv', 'z' => 'z2',
    ];

    /** Characters that mask a letter: "p*ta", "f**k". */
    private const MASKS = '*#';

    /**
     * Everything that can carry a letter, as a character class body. Used to
     * find word edges, so that "p u t @ n g i n a" reads as one spaced-out word
     * rather than two — the "@" has to count as a letter, not as the gap.
     */
    private const LETTERISH = 'a-z0-9@!|$()';

    /**
     * Endings that leave a term the same word: "gago" -> "gagong".
     *
     * Kept deliberately short. Every ending added here widens the net over
     * innocent words too — "y" alone turned "spicy" and "tangay" into hits, and
     * "in" turned the spice "cumin" into one. Terms that need broader coverage
     * should carry a "*" in the config instead.
     */
    private const SUFFIXES = ['s', 'es', 'ed', 'd', 'ing', 'ng'];

    /** Invisible characters pasted in to split a word: zero-width space and friends. */
    private const INVISIBLE = '/[\x{00AD}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}]/u';

    /** Terms and exceptions only change on deploy, so compiling once per process is enough. */
    private static array $compiled = [];

    private bool $enabled;

    private ?string $blocked;

    /** @var list<string> */
    private array $allowed;

    /**
     * @param  list<string>|null  $blocked  overrides config('profanity.blocked')
     * @param  list<string>|null  $allowed  overrides config('profanity.allowed')
     */
    public function __construct(?array $blocked = null, ?array $allowed = null, ?bool $enabled = null)
    {
        $this->enabled = $enabled ?? (bool) config('profanity.enabled', true);
        $this->blocked = $this->blockedPattern($blocked ?? (array) config('profanity.blocked', []));
        $this->allowed = $this->allowedPatterns($allowed ?? (array) config('profanity.allowed', []));
    }

    public function containsBlockedContent(string $text): bool
    {
        return $this->blockedTerms($text) !== [];
    }

    /**
     * The offending words, as they were written. Handy for tests and for
     * telling an admin why something was rejected.
     *
     * @return list<string>
     */
    public function blockedTerms(string $text): array
    {
        if (! $this->enabled || $this->blocked === null || trim($text) === '') {
            return [];
        }

        $found = [];

        foreach ($this->variants($text) as $variant) {
            if (preg_match_all($this->blocked, $variant, $matches) === 0) {
                continue;
            }

            foreach ($matches[0] as $match) {
                $found[$match] = true;
            }
        }

        return array_keys($found);
    }

    /**
     * The forms of the text worth scanning: the normalised text, plus — when it
     * differs — the same text with spaced-out letters pushed back together, so
     * "p u t a" and "f.u.c.k" are read as one word.
     *
     * @return list<string>
     */
    private function variants(string $text): array
    {
        $normalised = $this->normalise($text);
        $joined = $this->joinSpacedLetters($normalised);

        return $joined === $normalised ? [$normalised] : [$normalised, $joined];
    }

    private function normalise(string $text): string
    {
        $text = preg_replace(self::INVISIBLE, '', $text) ?? $text;
        $text = Str::lower(Str::ascii($text));

        // Lift out the known-innocent phrases first, so "leche flan" survives
        // while a bare "letse" still does not.
        foreach ($this->allowed as $pattern) {
            $text = preg_replace($pattern, ' ', $text) ?? $text;
        }

        return $text;
    }

    /**
     * Collapse runs of single letters split by punctuation or spaces
     * ("f u c k", "p.u.t.a"). Only runs of lone letters are joined, so ordinary
     * words are left alone and "a ssassin" never becomes a match.
     */
    private function joinSpacedLetters(string $text): string
    {
        $letter = '['.self::LETTERISH.']';
        $gap = '[^'.self::LETTERISH.']';

        return preg_replace_callback(
            '/(?<!'.$letter.')(?:'.$letter.$gap.'{1,2}){2,}'.$letter.'(?!'.$letter.')/',
            fn (array $match): string => preg_replace('/'.$gap.'/', '', $match[0]),
            $text,
        ) ?? $text;
    }

    /**
     * One alternation covering every blocked term, anchored so a term has to
     * start a word — that is what keeps "spic" out of "suspicious" and "ass"
     * out of "assignment".
     *
     * @param  list<string>  $terms
     */
    private function blockedPattern(array $terms): ?string
    {
        $terms = array_values(array_filter(array_map('trim', $terms)));

        if ($terms === []) {
            return null;
        }

        return self::$compiled['blocked:'.md5(serialize($terms))] ??= '/(?<![a-z0-9])(?:'
            .implode('|', array_map($this->termPattern(...), $terms))
            .')(?![a-z0-9])/u';
    }

    private function termPattern(string $term): string
    {
        // A trailing "*" means the term may run into a longer word.
        $open = str_ends_with($term, '*');
        $term = Str::lower(rtrim($term, '*'));

        $words = array_map($this->wordPattern(...), preg_split('/\s+/', $term));

        // Any punctuation may stand in for the space in a multi-word term.
        return implode('[^a-z0-9]{1,4}', $words)
            .($open ? '[a-z0-9]*' : '(?:'.implode('|', self::SUFFIXES).')?');
    }

    private function wordPattern(string $word): string
    {
        $pattern = '';

        foreach (str_split($word) as $index => $letter) {
            $letters = '['.preg_quote(self::LOOKALIKES[$letter] ?? $letter, '/').']+';

            // A letter may be repeated ("fuuuck") or masked ("f**k"), but the
            // first one has to be written out or "***" would match everything.
            $pattern .= $index === 0
                ? $letters
                : '(?:'.$letters.'|['.preg_quote(self::MASKS, '/').']+)';
        }

        return $pattern;
    }

    /**
     * @param  list<string>  $phrases
     * @return list<string>
     */
    private function allowedPatterns(array $phrases): array
    {
        $phrases = array_values(array_filter(array_map('trim', $phrases)));

        return self::$compiled['allowed:'.md5(serialize($phrases))] ??= array_map(
            fn (string $phrase): string => '/(?<![a-z0-9])'
                .implode('[^a-z0-9]+', array_map(
                    fn (string $word): string => preg_quote($word, '/'),
                    preg_split('/\s+/', Str::lower($phrase)),
                ))
                .'(?![a-z0-9])/u',
            $phrases,
        );
    }
}
