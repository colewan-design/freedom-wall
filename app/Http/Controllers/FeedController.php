<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $authorIds = Friendship::friendIdsFor($user)->push($user->id);

        $posts = Post::query()
            ->whereIn('user_id', $authorIds)
            ->with('user:id,name,username,avatar_path')
            ->latest()
            ->limit(50)
            ->get();

        $savedPostIds = $user->savedPosts()->pluck('posts.id');

        return Inertia::render('Feed/Index', [
            'profile' => $user->only(['name', 'username', 'avatar_url']),
            'posts' => $posts,
            'savedPostIds' => $savedPostIds,
        ]);
    }
}
