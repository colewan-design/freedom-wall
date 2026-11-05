<?php

namespace App\Services;

/**
 * What ContentRiskService made of one piece of text.
 *
 * A score, the categories that produced it, and the exact substrings that
 * tripped each one — the evidence matters as much as the score, because the
 * admin reading the queue needs to see *why* a post floated to the top before
 * they can disagree with it.
 */
class ContentRisk
{
    /**
     * @param  int  $score  0-100
     * @param  array<string, list<string>>  $flags  category => matched substrings
     */
    public function __construct(
        public readonly int $score,
        public readonly array $flags,
    ) {}

    public static function none(): self
    {
        return new self(0, []);
    }

    /** @return list<string> */
    public function categories(): array
    {
        return array_keys($this->flags);
    }

    public function has(string $category): bool
    {
        return isset($this->flags[$category]);
    }

    public function isClean(): bool
    {
        return $this->flags === [];
    }

    /**
     * The band this score falls in: none, low, medium or high. Thresholds live
     * in config/moderation.php so the dashboard and the model agree on them.
     */
    public function level(): string
    {
        $levels = (array) config('moderation.levels', []);

        foreach (['high', 'medium', 'low'] as $level) {
            if (isset($levels[$level]) && $this->score >= $levels[$level]) {
                return $level;
            }
        }

        return 'none';
    }
}
