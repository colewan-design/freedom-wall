<?php

namespace App\Http\Middleware;

use App\Models\Friendship;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            'pendingRequestCount' => fn () => $request->user()?->role === 'student'
                ? Friendship::query()->where('addressee_id', $request->user()->id)->where('status', 'pending')->count()
                : 0,
            'unreadMessageCount' => fn () => $request->user()?->role === 'student'
                ? Message::query()
                    ->join('conversation_participants as cp', function ($join) use ($request) {
                        $join->on('cp.conversation_id', '=', 'messages.conversation_id')
                            ->where('cp.user_id', $request->user()->id);
                    })
                    ->where('messages.user_id', '!=', $request->user()->id)
                    ->where(function ($q) {
                        $q->whereNull('cp.last_read_at')
                            ->orWhereColumn('messages.created_at', '>', 'cp.last_read_at');
                    })
                    ->count()
                : 0,
            'suggestedFriends' => fn () => $request->user()?->role === 'student'
                ? $this->suggestedFriendsFor($request->user())
                : [],
            'storyFriends' => fn () => $request->user()?->role === 'student'
                ? User::query()
                    ->whereIn('id', Friendship::friendIdsFor($request->user()))
                    ->limit(8)
                    ->get(['id', 'name', 'username', 'avatar_path'])
                : [],
        ];
    }

    private function suggestedFriendsFor(User $user): Collection
    {
        $excludedIds = Friendship::query()
            ->where('requester_id', $user->id)
            ->orWhere('addressee_id', $user->id)
            ->get()
            ->flatMap(fn (Friendship $f) => [$f->requester_id, $f->addressee_id])
            ->push($user->id)
            ->unique();

        return User::query()
            ->where('role', 'student')
            ->whereNotIn('id', $excludedIds)
            ->inRandomOrder()
            ->limit(4)
            ->get(['id', 'name', 'username', 'avatar_path']);
    }
}
