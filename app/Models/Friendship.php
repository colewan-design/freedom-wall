<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Friendship extends Model
{
    protected $fillable = ['requester_id', 'addressee_id', 'status'];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    /**
     * IDs of the given user's accepted friends.
     */
    public static function friendIdsFor(User $user): Collection
    {
        return static::query()
            ->where('status', 'accepted')
            ->where(function ($query) use ($user) {
                $query->where('requester_id', $user->id)
                    ->orWhere('addressee_id', $user->id);
            })
            ->get()
            ->map(fn (self $friendship) => $friendship->requester_id === $user->id
                ? $friendship->addressee_id
                : $friendship->requester_id);
    }
}
