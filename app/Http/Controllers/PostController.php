<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use App\Services\ContentFilterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
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
        $request->user()->savedPosts()->syncWithoutDetaching([$post->id]);

        return redirect()->back();
    }

    public function unsave(Request $request, Post $post): RedirectResponse
    {
        $request->user()->savedPosts()->detach($post->id);

        return redirect()->back();
    }
}
