<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThreadReply extends Model
{
    /**
     * Deepest indent level, counting the first reply as 0 — so three visible
     * levels. Past this a reply becomes a sibling of the one it answers rather
     * than a fourth column of indentation nobody can read on a phone.
     */
    public const MAX_DEPTH = 2;

    public const MAX_BODY_LENGTH = 2000;

    public $timestamps = false;

    protected $fillable = ['thread_id', 'parent_id', 'depth', 'body', 'author_token', 'ip_hash'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ThreadReplyVote::class);
    }

    public function scopeVisible(Builder $query): void
    {
        $query->where('status', 'visible');
    }

    public function isVisible(): bool
    {
        return $this->status === 'visible';
    }
}
