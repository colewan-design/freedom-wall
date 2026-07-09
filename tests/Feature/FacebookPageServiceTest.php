<?php

namespace Tests\Feature;

use App\Services\FacebookPageService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class FacebookPageServiceTest extends TestCase
{
    public function test_it_rewrites_deprecated_publish_actions_errors(): void
    {
        config([
            'services.facebook.page_id' => '123456789',
            'services.facebook.access_token' => 'page-token',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => '(#200) The permission(s) publish_actions are not available. It has been deprecated.',
                ],
            ], 400),
        ]);

        $service = app(FacebookPageService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('deprecated publish_actions permission');

        $service->post('Test post', null);
    }

    public function test_it_creates_a_multi_photo_page_post_when_multiple_images_are_provided(): void
    {
        config([
            'services.facebook.api_version' => 'v25.0',
            'services.facebook.page_id' => '123456789',
            'services.facebook.access_token' => 'page-token',
        ]);

        Storage::fake('public');
        Storage::disk('public')->put('uploads/first.jpg', 'first-image');
        Storage::disk('public')->put('uploads/second.jpg', 'second-image');

        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['id' => 'photo-1'], 200)
                ->push(['id' => 'photo-2'], 200)
                ->push(['id' => 'post-123'], 200),
        ]);

        $service = app(FacebookPageService::class);

        $postId = $service->post('Album post', [
            'https://freedom-wall.salidumay.com/media/uploads/first.jpg',
            'https://freedom-wall.salidumay.com/media/uploads/second.jpg',
        ]);

        $this->assertSame('post-123', $postId);
        Http::assertSentCount(3);

        Http::assertSent(function (Request $request) {
            $data = collect($request->data());

            return str_ends_with($request->url(), '/photos')
                && $data->contains(fn (array $part) => ($part['name'] ?? null) === 'published' && ($part['contents'] ?? null) === 'false')
                && $data->contains(fn (array $part) => ($part['name'] ?? null) === 'source');
        });

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return str_ends_with($request->url(), '/feed')
                && ($data['message'] ?? null) === 'Album post'
                && ($data['attached_media[0]'] ?? null) === '{"media_fbid":"photo-1"}'
                && ($data['attached_media[1]'] ?? null) === '{"media_fbid":"photo-2"}';
        });
    }
}
