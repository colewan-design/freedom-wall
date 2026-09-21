<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thread extends Model
{
    /**
     * The curated topics the composer offers and the sidebar filters by. A
     * poster can also write their own tag, exactly as on the wall — this list
     * is the shortcut, not the whole vocabulary.
     */
    public const TOPICS = ['confession', 'advice', 'campus', 'org', 'study', 'rant', 'marketplace'];

    public const MAX_TITLE_LENGTH = 160;

    public const MAX_BODY_LENGTH = 4000;

    protected $fillable = ['title', 'body', 'topic', 'author_token', 'ip_hash', 'last_activity_at'];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    public function replies(): HasMany
    {
        return $this->hasMany(ThreadReply::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ThreadReport::class);
    }

    public function scopeVisible(Builder $query): void
    {
        $query->where('status', 'visible');
    }

    /** Newest conversation first — a thread rises when someone replies in it. */
    public function scopeRecentlyActive(Builder $query): void
    {
        $query->orderByDesc('last_activity_at')->orderByDesc('id');
    }

    public function isVisible(): bool
    {
        return $this->status === 'visible';
    }

    /** Called whenever a reply lands, so the thread list reorders around it. */
    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->save();
    }
}
