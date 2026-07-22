<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = ['type', 'name', 'created_by'];

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('last_read_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('id');
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->whereKey($user->id)->exists();
    }

    /**
     * The existing direct conversation between two users, if any.
     */
    public static function directBetween(User $a, User $b): ?self
    {
        return static::query()
            ->where('type', 'direct')
            ->whereHas('participants', fn ($q) => $q->whereKey($a->id))
            ->whereHas('participants', fn ($q) => $q->whereKey($b->id))
            ->first();
    }

    public function displayNameFor(User $user): string
    {
        if ($this->type === 'group') {
            return $this->name ?? 'Group chat';
        }

        $other = $this->participants->firstWhere('id', '!=', $user->id);

        return $other?->name ?? 'Conversation';
    }
}
