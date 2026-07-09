<?php

namespace Tests\Unit;

use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionImageUrlTest extends TestCase
{
    public function test_it_normalizes_storage_urls_to_the_current_host(): void
    {
        config([
            'app.url' => 'https://freedom-wall.salidumay.com',
            'filesystems.disks.public.url' => 'https://freedom-wall.salidumay.com/media',
        ]);

        $submission = new Submission([
            'image_url' => 'http://127.0.0.1:8000/storage/uploads/first.jpg',
            'images' => [
                'http://localhost/storage/uploads/first.jpg',
                'https://old-domain.test/media/uploads/second.png',
            ],
        ]);

        $this->assertSame(
            Storage::disk('public')->url('uploads/first.jpg'),
            $submission->image_url,
        );

        $this->assertSame([
            Storage::disk('public')->url('uploads/first.jpg'),
            Storage::disk('public')->url('uploads/second.png'),
        ], $submission->image_urls);
    }
}
