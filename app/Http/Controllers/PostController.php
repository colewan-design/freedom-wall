<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Models\Friendship;
use App\Models\Post;
use App\Models\PostReaction;
use App\Services\ContentFilterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    private const REACTION_TYPES = ['fire', 'love', 'sad', 'heart'];

    public function store(StorePostRequest $request, ContentFilterService $contentFilter): RedirectResponse
    {
        $content = trim($request->string('content'));

        if ($contentFilter->containsBlockedContent($content)) {
            throw ValidationException::withMessages([
                'content' => 'Your post contains content that is not allowed.',
            ]);
        }

        $imagePaths = [];
        foreach ($request->file('images', []) as $image) {
            $filename = Str::uuid().'.'.$image->getClientOriginalExtension();
            $imagePaths[] = $image->storeAs('posts', $filename, 'public');
        }

        Post::create([
            'user_id' => $request->user()->id,
            'content' => $content,
            'images' => $imagePaths ?: null,
        ]);

        return redirect()->route('feed');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);

        foreach ($post->images ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }

        $post->delete();

        return redirect()->back();
    }

    public function save(Request $request, Post $post): RedirectResponse
    {
        $this->assertCanViewPost($request, $post);

        $request->user()->savedPosts()->syncWithoutDetaching([$post->id]);

        return redirect()->back();
    }

    public function unsave(Request $request, Post $post): RedirectResponse
    {
        $request->user()->savedPosts()->detach($post->id);

        return redirect()->back();
    }

    public function react(Request $request, Post $post): RedirectResponse
    {
        $this->assertCanViewPost($request, $post);

        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', self::REACTION_TYPES)],
        ]);

        PostReaction::updateOrCreate(
            ['post_id' => $post->id, 'user_id' => $request->user()->id],
            ['type' => $validated['type']],
        );

        return redirect()->back();
    }

    public function unreact(Request $request, Post $post): RedirectResponse
    {
        PostReaction::query()
            ->where('post_id', $post->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return redirect()->back();
    }

    public function storeComment(Request $request, Post $post, ContentFilterService $contentFilter): RedirectResponse
    {
        $this->assertCanViewPost($request, $post);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:500'],
        ]);

        $content = trim($validated['content']);

        if ($contentFilter->containsBlockedContent($content)) {
            throw ValidationException::withMessages([
                'content' => 'Your comment contains content that is not allowed.',
            ]);
        }

        $post->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $content,
        ]);

        return redirect()->back();
    }

    private function assertCanViewPost(Request $request, Post $post): void
    {
        $user = $request->user();

        if ($post->user_id === $user->id) {
            return;
        }

        abort_unless(Friendship::friendIdsFor($user)->contains($post->user_id), 403);
    }
}
