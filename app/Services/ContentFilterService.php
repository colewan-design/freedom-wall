<?php

namespace App\Services;

use Illuminate\Support\Str;

// Minimal first-pass filter to catch the worst content before it reaches the
// moderation queue. This does NOT replace human review — it only reduces
// admin burden by rejecting obvious slurs/spam outright.
class ContentFilterService
{
    private const BLOCKED_TERMS = [
        'nigger', 'faggot', 'retard', 'kike', 'chink', 'spic', 'tranny',
    ];

    public function containsBlockedContent(string $text): bool
    {
        $lower = Str::lower($text);

        foreach (self::BLOCKED_TERMS as $term) {
            if (str_contains($lower, $term)) {
                return true;
            }
        }

        return false;
    }
}
