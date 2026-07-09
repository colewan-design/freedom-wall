<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
                return array_values(array_filter($this->images));
            }

            return $this->image_url ? [$this->image_url] : [];
        });
    }
}
