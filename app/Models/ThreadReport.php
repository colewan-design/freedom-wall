<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadReport extends Model
{
    /** What a reporter can say is wrong, kept short so the queue stays sortable. */
    public const REASONS = ['harassment', 'personal-info', 'spam', 'hate', 'other'];

    public $timestamps = false;

    protected $fillable = ['thread_id', 'thread_reply_id', 'reason', 'reporter_token', 'ip_hash'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(ThreadReply::class, 'thread_reply_id');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', 'open');
    }
}
