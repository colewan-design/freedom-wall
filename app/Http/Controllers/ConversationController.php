<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->whereKey($user->id))
            ->with(['participants:id,name,username,avatar_path', 'latestMessage'])
            ->get();

        $unreadCounts = Message::query()
            ->join('conversation_participants as cp', function ($join) use ($user) {
                $join->on('cp.conversation_id', '=', 'messages.conversation_id')
                    ->where('cp.user_id', $user->id);
            })
            ->where('messages.user_id', '!=', $user->id)
            ->where(function ($q) {
                $q->whereNull('cp.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'cp.last_read_at');
            })
            ->groupBy('messages.conversation_id')
            ->selectRaw('messages.conversation_id, count(*) as unread')
            ->pluck('unread', 'messages.conversation_id');

        $items = $conversations
            ->sortByDesc(fn (Conversation $c) => $c->latestMessage?->created_at ?? $c->created_at)
            ->values()
            ->map(function (Conversation $c) use ($user, $unreadCounts) {
                $other = $c->type === 'direct'
                    ? $c->participants->firstWhere('id', '!=', $user->id)
                    : null;

                return [
                    'id' => $c->id,
                    'type' => $c->type,
                    'display_name' => $c->displayNameFor($user),
                    'avatar_url' => $other?->avatar_url,
                    'participant_count' => $c->participants->count(),
                    'last_message' => $c->latestMessage ? [
                        'content' => $c->latestMessage->content,
                        'created_at' => $c->latestMessage->created_at,
                        'is_own' => $c->latestMessage->user_id === $user->id,
                    ] : null,
                    'unread_count' => $unreadCounts[$c->id] ?? 0,
                ];
            });

        $friends = User::query()
            ->whereIn('id', Friendship::friendIdsFor($user))
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'avatar_path']);

        return Inertia::render('Messages/Index', [
            'conversations' => $items,
            'friends' => $friends,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'type' => ['required', 'in:direct,group'],
            'username' => ['required_if:type,direct', 'string', 'nullable'],
            'name' => ['required_if:type,group', 'string', 'nullable', 'max:80'],
            'participants' => ['required_if:type,group', 'array', 'nullable', 'min:2'],
            'participants.*' => ['integer'],
        ]);

        $friendIds = Friendship::friendIdsFor($user);

        if ($validated['type'] === 'direct') {
            $target = User::query()
                ->where('username', $validated['username'])
                ->firstOrFail();

            if (! $friendIds->contains($target->id)) {
                throw ValidationException::withMessages([
                    'username' => 'You can only message accepted friends.',
                ]);
            }

            if ($existing = Conversation::directBetween($user, $target)) {
                return redirect()->route('conversations.show', $existing);
            }

            $conversation = Conversation::create([
                'type' => 'direct',
                'created_by' => $user->id,
            ]);
            $conversation->participants()->attach([$user->id, $target->id]);

            return redirect()->route('conversations.show', $conversation);
        }

        $memberIds = collect($validated['participants'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $id === $user->id)
            ->values();

        if ($memberIds->count() < 2) {
            throw ValidationException::withMessages([
                'participants' => 'Pick at least two friends for a group chat.',
            ]);
        }

        if ($memberIds->diff($friendIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'participants' => 'Group members must be your accepted friends.',
            ]);
        }

        $conversation = Conversation::create([
            'type' => 'group',
            'name' => $validated['name'],
            'created_by' => $user->id,
        ]);
        $conversation->participants()->attach($memberIds->push($user->id)->all());

        return redirect()->route('conversations.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $user = $request->user();

        abort_unless($conversation->hasParticipant($user), 403);

        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        $conversation->load('participants:id,name,username,avatar_path');

        $messages = $conversation->messages()
            ->with('user:id,name,username,avatar_path')
            ->latest('id')
            ->limit(50)
            ->get()
            ->sortBy('id')
            ->values();

        return Inertia::render('Messages/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'display_name' => $conversation->displayNameFor($user),
                'participants' => $conversation->participants->map(
                    fn (User $p) => $p->only(['id', 'name', 'username', 'avatar_url']),
                ),
            ],
            'messages' => $messages,
        ]);
    }

    public function leave(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();

        abort_unless($conversation->hasParticipant($user) && $conversation->type === 'group', 403);

        $conversation->participants()->detach($user->id);

        return redirect()->route('messages.index');
    }
}
