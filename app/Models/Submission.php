<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Submission extends Model
{
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

    public function scopeFailedFacebook(Builder $query): void
    {
        $query->where('status', 'approved')->whereNull('fb_post_id');
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

        if (is_string($path) && str_starts_with($path, '/storage/')) {
            return Storage::disk('public')->url(preg_replace('#^/storage/#', '', $path));
        }

        return $imageUrl;
    }
}
