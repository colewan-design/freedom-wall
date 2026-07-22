<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedPostController extends Controller
{
    public function index(Request $request): Response
    {
        $posts = $request->user()
            ->savedPosts()
            ->with('user:id,name,username,avatar_path')
            ->latest('post_saves.created_at')
            ->get(['posts.id', 'posts.user_id', 'posts.content', 'posts.images', 'posts.created_at']);

        return Inertia::render('Saved/Index', [
            'posts' => $posts,
        ]);
    }
}
