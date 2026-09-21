<?php

namespace App\Rules;

use App\Models\Submission;
use App\Services\ContentFilterService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A post's hashtag: one of the suggested categories, or one the poster wrote.
 *
 * The allowed characters are not a style choice. The wall has no category
 * column — the tag is appended to the post body and read back out with
 * /#([\w-]+)\s*$/, so a tag containing anything else would neither render as a
 * hashtag nor be stripped from the body text.
 */
class Hashtag implements ValidationRule
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 30;

    /**
     * @param  list<string>  $allowed  The curated chips that bypass the spelling
     *                                 rules. Defaults to the wall's categories;
     *                                 threads pass their own topic list.
     */
    public function __construct(private readonly array $allowed = Submission::CATEGORIES) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Please pick or write a hashtag for your post.');

            return;
        }

        // The curated chips are always fine, whatever else the rules say.
        if (in_array($value, $this->allowed, true)) {
            return;
        }

        if (preg_match('/^[\w-]+$/', $value) !== 1) {
            $fail('A hashtag can only use letters, numbers, underscores, and hyphens — no spaces or punctuation.');

            return;
        }

        if (strlen($value) < self::MIN_LENGTH) {
            $fail('A hashtag needs at least '.self::MIN_LENGTH.' characters.');

            return;
        }

        if (strlen($value) > self::MAX_LENGTH) {
            $fail('A hashtag can be at most '.self::MAX_LENGTH.' characters.');

            return;
        }

        if (preg_match('/[a-z]/i', $value) !== 1) {
            $fail('A hashtag needs at least one letter.');

            return;
        }

        // The tag is appended to the post and shown on the wall, so it has to
        // clear the same filter the body does — otherwise it is a way around it.
        if (app(ContentFilterService::class)->containsBlockedContent($value)) {
            $fail('That hashtag contains content that is not allowed.');
        }
    }
}
