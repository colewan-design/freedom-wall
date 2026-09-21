<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadReplyVote extends Model
{
    protected $fillable = ['thread_reply_id', 'voter_token', 'value'];

    protected $casts = [
        'value' => 'integer',
    ];

    public function reply(): BelongsTo
    {
        return $this->belongsTo(ThreadReply::class, 'thread_reply_id');
    }
}
