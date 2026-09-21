<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubmissionRequest;
use App\Models\Submission;
use App\Rules\MediaAttachment;
use App\Services\ContentFilterService;
use App\Services\IpHasher;
use App\Services\TurnstileService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
    private const WALL_POST_LIMIT = 50;

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

        $mediaUrls = [];
        foreach ($request->file('images', []) as $file) {
            $filename = Str::uuid().'.'.MediaAttachment::extensionFor($file);
            $path = $file->storeAs('uploads', $filename, 'public');
            $mediaUrls[] = Storage::disk('public')->url($path);
        }

        Submission::create([
            'content' => $content,
            'image_url' => $mediaUrls[0] ?? null,
            'images' => $mediaUrls ?: null,
            'ip_hash' => $ipHasher->hash($request->ip()),
        ]);

        return redirect()->route('wall')->with('success', 'Submitted! Our team will review it before posting.');
    }

    public function wall(): Response
    {
        $posts = Submission::query()
            ->approved()
            ->orderByDesc('reviewed_at')
            ->limit(self::WALL_POST_LIMIT)
            ->get(['id', 'content', 'image_url', 'images', 'reviewed_at']);

        return Inertia::render('Wall', [
            'posts' => $posts,
            'categories' => Submission::CATEGORIES,
            'stats' => $this->wallStats(),
        ]);
    }

    /**
     * Counted straight from the database rather than from $posts, which only
     * holds the most recent WALL_POST_LIMIT rows — the totals describe the whole
     * wall, not the slice currently rendered.
     */
    private function wallStats(): array
    {
        $total = Submission::query()->approved()->count();

        $withPhotos = Submission::query()
            ->approved()
            ->where(function (Builder $query) {
                $query
                    ->whereJsonLength('images', '>', 0)
                    ->orWhere(function (Builder $legacy) {
                        // Rows predating the images column still carry a single image_url.
                        $legacy->whereNotNull('image_url')->where('image_url', '!=', '');
                    });
            })
            ->count();

        return [
            'total' => $total,
            'withPhotos' => $withPhotos,
            'textOnly' => $total - $withPhotos,
        ];
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
