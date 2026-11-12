<?php

namespace App\Services\Jobs;

/**
 * The key two postings of the same vacancy share: normalised title plus
 * normalised employer.
 *
 * Location is left out on purpose. Boards disagree on it far more than on the
 * title ("Baguio" / "Baguio City, Benguet" / blank), and keeping it would split
 * the duplicates this exists to merge. The cost is that one employer hiring the
 * same title in two cities becomes one listing — it keeps every source link,
 * so the second city is still one click away.
 */
final class JobFingerprint
{
    public static function for(string $title, ?string $employer): string
    {
        // Noise words that boards bolt onto the same opening.
        $title = preg_replace(
            '/\b(urgent(ly)?|hiring|now|wanted|needed|job|opening|vacancy|position|immediate)\b/',
            ' ',
            JobText::normalize($title),
        ) ?? '';

        $parts = [trim(preg_replace('/\s+/', ' ', $title) ?? ''), JobText::normalize($employer)];

        return hash('sha256', implode('|', $parts));
    }
}
