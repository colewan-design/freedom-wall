<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A saved search that keeps running after the student closes the tab. */
class JobMonitor extends Model
{
    public const FREQUENCIES = ['daily', 'weekly'];

    /** Enough to cover a few careers without turning into a scraper of our own. */
    public const MAX_PER_USER = 5;

    public const MAX_KEYWORDS = 8;

    protected $fillable = [
        'user_id', 'name', 'keywords', 'location', 'employment_types',
        'remote_ok', 'min_score', 'frequency', 'email_alerts', 'is_active',
    ];

    protected $casts = [
        'keywords' => 'array',
        'employment_types' => 'array',
        'remote_ok' => 'boolean',
        'email_alerts' => 'boolean',
        'is_active' => 'boolean',
        'min_score' => 'integer',
        'last_digest_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hits(): HasMany
    {
        return $this->hasMany(JobMonitorHit::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Whether this monitor's digest is due, given when it last went out. */
    public function digestIsDue(): bool
    {
        if ($this->last_digest_at === null) {
            return true;
        }

        $interval = $this->frequency === 'weekly' ? now()->subWeek() : now()->subDay();

        // Small slack so a digest scheduled at 07:00 is not skipped because
        // yesterday's run finished at 07:00:40.
        return $this->last_digest_at->lte($interval->addMinutes(10));
    }
}
