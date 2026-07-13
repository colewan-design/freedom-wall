<?php

namespace Tests\Feature;

use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreSubmissionImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_multiple_submission_images(): void
    {
        Storage::fake('public');

        $response = $this->post('/submissions', [
            'content' => 'A post with more than one image.',
            'images' => [
                UploadedFile::fake()->image('first.jpg'),
                UploadedFile::fake()->image('second.png'),
            ],
            'captchaToken' => 'test-token',
        ]);

        $response->assertRedirect(route('wall'));
        $response->assertSessionHas('success');

        $submission = Submission::query()->firstOrFail();

        $this->assertCount(2, $submission->images);
        $this->assertSame($submission->images[0], $submission->image_url);

        foreach ($submission->images as $imageUrl) {
            $path = ltrim(parse_url($imageUrl, PHP_URL_PATH) ?? '', '/');
            $storagePath = preg_replace('#^storage/#', '', $path);
            Storage::disk('public')->assertExists($storagePath);
        }
    }
}
