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
    public function create(): Response
    {
        return Inertia::render('Submit');
    }

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

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $filename = Str::uuid().'.'.$request->file('image')->getClientOriginalExtension();
            $path = $request->file('image')->storeAs('uploads', $filename, 'public');
            $imageUrl = Storage::disk('public')->url($path);
        }

        Submission::create([
            'content' => $content,
            'image_url' => $imageUrl,
            'ip_hash' => $ipHasher->hash($request->ip()),
        ]);

        return redirect()->route('submit')->with('success', 'Submitted! Our team will review it before posting.');
    }

    public function wall(): Response
    {
        $posts = Submission::query()
            ->approved()
            ->orderByDesc('reviewed_at')
            ->limit(50)
            ->get(['id', 'content', 'image_url', 'reviewed_at', 'fb_post_id']);

        return Inertia::render('Wall', [
            'posts' => $posts,
        ]);
    }
}
