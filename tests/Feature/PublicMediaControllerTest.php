<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicMediaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_serves_public_media_without_a_symlink(): void
    {
        Storage::disk('public')->put('uploads/example.txt', 'hello world');

        $response = $this->get('/media/uploads/example.txt');

        $response->assertOk();
        $response->assertSeeText('hello world');
    }
}
