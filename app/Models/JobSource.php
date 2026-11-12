<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobSource extends Model
{
    /**
     * Every driver reads a feed or an API the publisher put there for this.
     * Pages that only exist as HTML are not on the list on purpose — that is
     * scraping, and Facebook groups in particular forbid it outright.
     */
    public const DRIVERS = [
        'rss' => 'RSS / Atom feed',
        'remotive' => 'Remotive (remote jobs API)',
        'greenhouse' => 'Greenhouse job board',
        'lever' => 'Lever job board',
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
