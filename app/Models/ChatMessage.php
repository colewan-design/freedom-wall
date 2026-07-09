<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    public $timestamps = false;

    protected $fillable = ['nickname', 'content', 'ip_hash'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByDesc('id');
    }
}
