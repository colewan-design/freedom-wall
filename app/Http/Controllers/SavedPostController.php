<?php

namespace App\Http\Controllers;

use App\Models\PostReaction;
use App\Models\PostView;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedPostController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $posts = $user
            ->savedPosts()
            ->with(['user:id,name,username,avatar_path', 'comments.user:id,name,username,avatar_path'])
            ->withCount(['reactions', 'comments', 'views'])
            ->latest('post_saves.created_at')
            ->get(['posts.id', 'posts.user_id', 'posts.content', 'posts.images', 'posts.created_at']);

        foreach ($posts as $post) {
            if ($post->user_id !== $user->id) {
                PostView::firstOrCreate(['post_id' => $post->id, 'user_id' => $user->id]);
            }
        }

        $viewerReactions = PostReaction::query()
            ->where('user_id', $user->id)
            ->whereIn('post_id', $posts->pluck('id'))
            ->pluck('type', 'post_id');

        return Inertia::render('Saved/Index', [
            'posts' => $posts,
            'viewerReactions' => $viewerReactions,
        ]);
    }
}
