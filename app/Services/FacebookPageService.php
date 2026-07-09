<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FacebookPageService
{
    public function post(string $message, array|string|null $imageUrls): string
    {
        $imageUrls = array_values(array_filter((array) $imageUrls));

        return match (count($imageUrls)) {
            0 => $this->postText($message),
            1 => $this->postPhoto($message, $imageUrls[0]),
            default => $this->postMultiPhoto($message, $imageUrls),
        };
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
        $response = Http::attach(...$this->photoAttachment($imageUrl))
            ->post($this->endpoint('photos'), [
                'caption' => $message,
                'access_token' => $this->accessToken(),
            ]);

        $this->throwIfFailed($response);

        return $response->json('post_id') ?? $response->json('id');
    }

    private function postMultiPhoto(string $message, array $imageUrls): string
    {
        $attachedMedia = [];

        foreach ($imageUrls as $index => $imageUrl) {
            $mediaId = $this->uploadUnpublishedPhoto($imageUrl);
            $attachedMedia["attached_media[{$index}]"] = json_encode(['media_fbid' => $mediaId]);
        }

        $response = Http::asForm()->post($this->endpoint('feed'), [
            'message' => $message,
            'access_token' => $this->accessToken(),
            ...$attachedMedia,
        ]);

        $this->throwIfFailed($response);

        return $response->json('id');
    }

    private function uploadUnpublishedPhoto(string $imageUrl): string
    {
        $response = Http::attach(...$this->photoAttachment($imageUrl))
            ->post($this->endpoint('photos'), [
                'published' => 'false',
                'access_token' => $this->accessToken(),
            ]);

        $this->throwIfFailed($response);

        return (string) $response->json('id');
    }

    private function photoAttachment(string $imageUrl): array
    {
        $path = $this->relativeUploadPath($imageUrl);

        if (! Storage::disk('public')->exists($path)) {
            throw new RuntimeException('Image file for this submission could not be found');
        }

        return ['source', Storage::disk('public')->get($path), basename($path)];
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
