<?php

namespace Tests\Feature;

use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeVideo;
use Tests\TestCase;

class StoreSubmissionVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_a_video_under_a_minute(): void
    {
        Storage::fake('public');

        $response = $this->submit(UploadedFile::fake()->createWithContent('clip.mp4', FakeVideo::mp4(45)));

        $response->assertRedirect(route('wall'));
        $response->assertSessionHasNoErrors();

        $submission = Submission::query()->firstOrFail();

        $this->assertCount(1, $submission->images);
        $this->assertStringEndsWith('.mp4', $submission->images[0]);
    }

    public function test_it_rejects_a_video_over_a_minute(): void
    {
        Storage::fake('public');

        $response = $this->submit(UploadedFile::fake()->createWithContent('long.mp4', FakeVideo::mp4(95)));

        $response->assertSessionHasErrors('images.0');
        $this->assertSame(0, Submission::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_it_allows_a_clip_that_overshoots_a_minute_by_a_hair(): void
    {
        Storage::fake('public');

        $this->submit(UploadedFile::fake()->createWithContent('just-over.mp4', FakeVideo::mp4(60.2)))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Submission::query()->count());
    }

    public function test_it_rejects_a_video_it_cannot_read(): void
    {
        Storage::fake('public');

        $response = $this->submit(UploadedFile::fake()->createWithContent('broken.mp4', 'not really a video'));

        $response->assertSessionHasErrors('images.0');
        $this->assertSame(0, Submission::query()->count());
    }

    public function test_it_allows_only_one_video_per_post(): void
    {
        Storage::fake('public');

        $response = $this->submit(
            UploadedFile::fake()->createWithContent('one.mp4', FakeVideo::mp4(10)),
            UploadedFile::fake()->createWithContent('two.webm', FakeVideo::webm(10)),
        );

        $response->assertSessionHasErrors('images');
        $this->assertSame(0, Submission::query()->count());
    }

    public function test_it_still_rejects_file_types_that_are_neither_photo_nor_video(): void
    {
        Storage::fake('public');

        $response = $this->submit(UploadedFile::fake()->create('notes.pdf', 12));

        $response->assertSessionHasErrors('images.0');
        $this->assertSame(0, Submission::query()->count());
    }

    private function submit(UploadedFile ...$files): TestResponse
    {
        return $this->post('/submissions', [
            'content' => 'A post with an attachment.',
            'category' => 'confessions',
            'images' => $files,
            'captchaToken' => 'test-token',
        ]);
    }
}
