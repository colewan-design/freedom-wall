<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FriendController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $friendships = Friendship::query()
            ->where('requester_id', $user->id)
            ->orWhere('addressee_id', $user->id)
            ->with(['requester:id,name,username,avatar_path', 'addressee:id,name,username,avatar_path'])
            ->get();

        $friends = $friendships
            ->where('status', 'accepted')
            ->map(fn (Friendship $f) => [
                'friendship_id' => $f->id,
                'user' => $f->requester_id === $user->id ? $f->addressee : $f->requester,
            ])
            ->values();

        $incoming = $friendships
            ->where('status', 'pending')
            ->where('addressee_id', $user->id)
            ->values();

        $outgoing = $friendships
            ->where('status', 'pending')
            ->where('requester_id', $user->id)
            ->values();

        $searchResults = [];
        if ($query = $request->string('q')->trim()->value()) {
            $friendIds = $friendships->pluck('requester_id')->merge($friendships->pluck('addressee_id'));

            $searchResults = User::query()
                ->where('role', 'student')
                ->where('id', '!=', $user->id)
                ->where('username', 'like', "%{$query}%")
                ->whereNotIn('id', $friendIds)
                ->limit(10)
                ->get(['id', 'name', 'username', 'avatar_path']);
        }

        return Inertia::render('Friends/Index', [
            'friends' => $friends,
            'incoming' => $incoming,
            'outgoing' => $outgoing,
            'searchResults' => $searchResults,
            'query' => $query ?? '',
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $requester = $request->user();

        abort_if($user->id === $requester->id, 422, 'You cannot friend yourself.');

        $exists = Friendship::query()
            ->where(function ($q) use ($requester, $user) {
                $q->where('requester_id', $requester->id)->where('addressee_id', $user->id);
            })
            ->orWhere(function ($q) use ($requester, $user) {
                $q->where('requester_id', $user->id)->where('addressee_id', $requester->id);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'friend' => 'A friend request already exists with this student.',
            ]);
        }

        Friendship::create([
            'requester_id' => $requester->id,
            'addressee_id' => $user->id,
            'status' => 'pending',
        ]);

        return redirect()->back();
    }

    public function accept(Request $request, Friendship $friendship): RedirectResponse
    {
        abort_unless($friendship->addressee_id === $request->user()->id, 403);

        $friendship->update(['status' => 'accepted']);

        return redirect()->back();
    }

    public function destroy(Request $request, Friendship $friendship): RedirectResponse
    {
        abort_unless(
            in_array($request->user()->id, [$friendship->requester_id, $friendship->addressee_id], true),
            403
        );

        $friendship->delete();

        return redirect()->back();
    }
}
