<?php

namespace Tests\Feature;

use App\Services\FacebookPageService;
use Illuminate\Support\Facades\Http;
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
}
