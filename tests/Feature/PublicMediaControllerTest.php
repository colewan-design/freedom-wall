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
        Storage::fake('public');

        Storage::disk('public')->put('uploads/example.txt', 'hello world');

        $response = $this->get('/media/uploads/example.txt');

        $response->assertOk();
        $this->assertSame('hello world', $response->getFile()->getContent());
    }

    public function test_it_answers_byte_ranges_so_videos_can_be_scrubbed(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('uploads/clip.mp4', 'hello world');

        $response = $this->get('/media/uploads/clip.mp4', ['Range' => 'bytes=6-10']);

        $response->assertStatus(206);
        $response->assertHeader('Content-Range', 'bytes 6-10/11');
    }

    public function test_it_refuses_to_walk_out_of_the_media_directory(): void
    {
        $this->get('/media/uploads/../../../.env')->assertNotFound();
    }
}
