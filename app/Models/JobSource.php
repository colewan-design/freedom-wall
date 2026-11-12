<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobSource extends Model
{
    /**
     * Feeds and official APIs first. `page` reads a public vacancy page that
     * has no feed, but only where robots.txt allows it, at most every six
     * hours, and never past a block or a bot challenge. Facebook pages and
     * groups stay off the list: their terms forbid automated reading outright.
     */
    public const DRIVERS = [
        'rss' => 'RSS / Atom feed',
        'remotive' => 'Remotive (remote jobs API)',
        'greenhouse' => 'Greenhouse job board',
        'lever' => 'Lever job board',
        'page' => 'Web page listing job links',
    ];

    protected $fillable = ['name', 'driver', 'config', 'is_active', 'auto_approve'];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'auto_approve' => 'boolean',
        'last_fetched_at' => 'datetime',
        'last_found_count' => 'integer',
    ];

    public function seen(): HasMany
    {
        return $this->hasMany(JobListingSource::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
