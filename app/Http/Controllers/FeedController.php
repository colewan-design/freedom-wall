<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\PostView;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $authorIds = Friendship::friendIdsFor($user)->push($user->id);

        $sort = $request->query('sort', 'friends');

        $posts = Post::query()
            ->whereIn('user_id', $authorIds)
            ->with(['user:id,name,username,avatar_path', 'comments.user:id,name,username,avatar_path'])
            ->withCount(['reactions', 'comments', 'views'])
            ->latest()
            ->limit(50)
            ->get();

        if ($sort === 'popular') {
            $posts = $posts
                ->sortByDesc(fn (Post $post) => $post->reactions_count + $post->comments_count + $post->views_count)
                ->values();
        }

        foreach ($posts as $post) {
            if ($post->user_id !== $user->id) {
                PostView::firstOrCreate(['post_id' => $post->id, 'user_id' => $user->id]);
            }
        }

        $postIds = $posts->pluck('id');

        $viewerReactions = PostReaction::query()
            ->where('user_id', $user->id)
            ->whereIn('post_id', $postIds)
            ->pluck('type', 'post_id');

        $savedPostIds = $user->savedPosts()->pluck('posts.id');

        return Inertia::render('Feed/Index', [
            'profile' => $user->only(['name', 'username', 'avatar_url']),
            'posts' => $posts,
            'savedPostIds' => $savedPostIds,
            'viewerReactions' => $viewerReactions,
            'sort' => $sort,
        ]);
    }
}
