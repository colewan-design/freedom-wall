<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FacebookPageService
{
    public function post(string $message, ?string $imageUrl): string
    {
        return $imageUrl
            ? $this->postPhoto($message, $imageUrl)
            : $this->postText($message);
    }

    private function postText(string $message): string
    {
        $response = Http::asForm()->post($this->endpoint('feed'), [
            'message' => $message,
            'access_token' => $this->accessToken(),
        ]);

        $this->throwIfFailed($response);

        return $response->json('id');
    }

    private function postPhoto(string $message, string $imageUrl): string
    {
        $path = $this->relativeUploadPath($imageUrl);

        if (! Storage::disk('public')->exists($path)) {
            throw new RuntimeException('Image file for this submission could not be found');
        }

        $response = Http::attach('source', Storage::disk('public')->get($path), basename($path))
            ->post($this->endpoint('photos'), [
                'caption' => $message,
                'access_token' => $this->accessToken(),
            ]);

        $this->throwIfFailed($response);

        return $response->json('post_id') ?? $response->json('id');
    }

    private function relativeUploadPath(string $imageUrl): string
    {
        // imageUrl is stored as an absolute URL (e.g.
        // "http://host/media/uploads/xxx.jpg") — translate back to the
        // disk-relative path ("uploads/xxx.jpg").
        $path = parse_url($imageUrl, PHP_URL_PATH);

        return preg_replace('#^/(storage|media)/#', '', $path);
    }

    private function endpoint(string $edge): string
    {
        $apiVersion = config('services.facebook.api_version');
        $pageId = config('services.facebook.page_id');

        return "https://graph.facebook.com/{$apiVersion}/{$pageId}/{$edge}";
    }

    private function accessToken(): string
    {
        $pageId = config('services.facebook.page_id');
        $accessToken = config('services.facebook.access_token');

        if (! $pageId || ! $accessToken) {
            throw new RuntimeException('Facebook Page ID or access token is not configured');
        }

        return $accessToken;
    }

    private function throwIfFailed($response): void
    {
        if ($response->failed()) {
            throw new RuntimeException($this->normalizeErrorMessage($response->json('error.message')));
        }
    }

    private function normalizeErrorMessage(?string $message): string
    {
        if (! $message) {
            return 'Facebook post failed';
        }

        if (str_contains($message, 'publish_actions')) {
            return 'The configured Facebook token is using the deprecated publish_actions permission. Replace FB_PAGE_ACCESS_TOKEN with a Page or System User token that has pages_manage_posts for this Page.';
        }

        return $message;
    }
}
