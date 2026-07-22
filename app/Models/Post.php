<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    protected $fillable = ['user_id', 'content', 'images'];

    protected $appends = ['image_urls'];

    protected $casts = [
        'images' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_saves')->withTimestamps();
    }

    protected function imageUrls(): Attribute
    {
        return Attribute::get(function (): array {
            if (! is_array($this->images) || $this->images === []) {
                return [];
            }

            return array_values(array_filter(array_map(
                fn ($path) => Storage::disk('public')->url($path),
                $this->images,
            )));
        });
    }
}
