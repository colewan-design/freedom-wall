<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

    protected $fillable = ['content', 'image_url', 'images', 'ip_hash'];

    protected $appends = ['image_urls'];

    protected $casts = [
        'images' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'was_edited' => 'boolean',
    ];

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', 'approved');
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
