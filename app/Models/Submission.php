<?php

namespace App\Models;

use App\Services\ContentRisk;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Submission extends Model
{
    /**
     * Every submission has to carry exactly one of these hashtags. The list is
     * the single source of truth: the form request validates against it and the
     * wall page renders the composer chips from it.
     */
    public const CATEGORIES = ['confessions', 'rant', 'kilig', 'question', 'announcement'];

    public $timestamps = false;

    protected $fillable = [
        'content', 'image_url', 'images', 'ip_hash',
        'risk_score', 'risk_flags', 'risk_checked_at',
    ];

    // risk_level is deliberately not appended: the public wall selects its own
    // narrow column list and has no business carrying moderation signals. The
    // admin queue appends it per query instead.
    protected $appends = ['image_urls'];

    protected $casts = [
        'images' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'was_edited' => 'boolean',
        'risk_flags' => 'array',
        'risk_checked_at' => 'datetime',
        'risk_score' => 'integer',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(SubmissionVote::class);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', 'approved');
    }

    /**
     * Worst first, then oldest first — so a flagged post never waits behind a
     * day of clean ones, and two posts at the same risk still queue fairly.
     */
    public function scopeByRisk(Builder $query): void
    {
        $query->orderByDesc('risk_score')->orderBy('submitted_at');
    }

    /**
     * The band this submission's score falls in. Reads the thresholds through
     * ContentRisk so the dashboard, the assessor and the model never drift.
     */
    protected function riskLevel(): Attribute
    {
        return Attribute::get(
            fn (): string => (new ContentRisk((int) $this->risk_score, (array) $this->risk_flags))->level(),
        );
    }

    protected function imageUrls(): Attribute
    {
        return Attribute::get(function (): array {
            if (is_array($this->images) && $this->images !== []) {
                return array_values(array_filter(array_map(
                    fn ($imageUrl) => $this->normalizeImageUrl($imageUrl),
                    $this->images,
                )));
            }

            $imageUrl = $this->getRawOriginal('image_url');

            return $imageUrl ? [$this->normalizeImageUrl($imageUrl)] : [];
        });
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (?string $value) => $this->normalizeImageUrl($value));
    }

    private function normalizeImageUrl(?string $imageUrl): ?string
    {
        if (! $imageUrl) {
            return null;
        }

        $path = parse_url($imageUrl, PHP_URL_PATH);

        if (is_string($path) && preg_match('#^/(storage|media)/#', $path)) {
            return Storage::disk('public')->url(preg_replace('#^/(storage|media)/#', '', $path));
        }

        return $imageUrl;
    }
}
