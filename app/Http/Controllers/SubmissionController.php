<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubmissionRequest;
use App\Models\Submission;
use App\Services\ContentFilterService;
use App\Services\IpHasher;
use App\Services\TurnstileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
    public function store(
        StoreSubmissionRequest $request,
        ContentFilterService $contentFilter,
        TurnstileService $turnstile,
        IpHasher $ipHasher,
    ): RedirectResponse {
        $content = trim($request->string('content'));

        if ($contentFilter->containsBlockedContent($content)) {
            throw ValidationException::withMessages([
                'content' => 'Your submission contains content that is not allowed.',
            ]);
        }

        if (! $turnstile->verify($request->input('captchaToken'), $request->ip())) {
            throw ValidationException::withMessages([
                'captchaToken' => 'CAPTCHA verification failed. Please try again.',
            ]);
        }

        $content = $this->withCategoryHashtag($content, $request->string('category')->toString());

        $imageUrls = [];
        foreach ($request->file('images', []) as $image) {
            $filename = Str::uuid().'.'.$image->getClientOriginalExtension();
            $path = $image->storeAs('uploads', $filename, 'public');
            $imageUrls[] = Storage::disk('public')->url($path);
        }

        Submission::create([
            'content' => $content,
            'image_url' => $imageUrls[0] ?? null,
            'images' => $imageUrls ?: null,
            'ip_hash' => $ipHasher->hash($request->ip()),
        ]);

        return redirect()->route('wall')->with('success', 'Submitted! Our team will review it before posting.');
    }

    public function wall(): Response
    {
        $posts = Submission::query()
            ->approved()
            ->orderByDesc('reviewed_at')
            ->limit(50)
            ->get(['id', 'content', 'image_url', 'images', 'reviewed_at']);

        return Inertia::render('Wall', [
            'posts' => $posts,
            'categories' => Submission::CATEGORIES,
        ]);
    }

    /**
     * Stamp the chosen category hashtag onto the end of the post. Posts that
     * already end with it (a resubmit, or someone who typed it themselves) are
     * left alone so the tag never doubles up.
     */
    private function withCategoryHashtag(string $content, string $category): string
    {
        $hashtag = '#'.$category;
        $content = rtrim($content);

        if (preg_match('/(^|\s)'.preg_quote($hashtag, '/').'\s*$/i', $content) === 1) {
            return $content;
        }

        return $content === '' ? $hashtag : $content."\n\n".$hashtag;
    }
}
