<?php

namespace Tests\Feature;

use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WallStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_totals_count_every_approved_post_not_just_the_rendered_page(): void
    {
        // 55 approved, which is past the 50-row slice the wall actually renders.
        $this->makeSubmissions(50);
        $this->makeSubmissions(3, ['images' => ['/storage/posts/a.jpg', '/storage/posts/b.jpg']]);
        $this->makeSubmissions(2, ['image_url' => '/storage/uploads/legacy.jpg']);

        $this->get(route('wall'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Wall')
                ->has('posts', 50)
                ->where('stats.total', 55)
                ->where('stats.withPhotos', 5)
                ->where('stats.textOnly', 50));
    }

    public function test_the_totals_ignore_pending_and_rejected_submissions(): void
    {
        $this->makeSubmissions(4);
        $this->makeSubmissions(3, ['status' => 'pending', 'reviewed_at' => null]);
        $this->makeSubmissions(2, ['status' => 'rejected']);

        $this->get(route('wall'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.total', 4)
                ->where('stats.textOnly', 4));
    }

    public function test_an_empty_images_array_does_not_count_as_a_photo_post(): void
    {
        $this->makeSubmissions(1, ['images' => []]);
        $this->makeSubmissions(1, ['images' => ['/storage/posts/real.jpg']]);

        $this->get(route('wall'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.total', 2)
                ->where('stats.withPhotos', 1)
                ->where('stats.textOnly', 1));
    }

    private function makeSubmissions(int $count, array $attributes = []): void
    {
        foreach (range(1, $count) as $index) {
            (new Submission)->forceFill(array_merge([
                'content' => "Approved post {$index}",
                'status' => 'approved',
                'ip_hash' => 'test-hash',
                'submitted_at' => now(),
                'reviewed_at' => now()->subMinutes($index),
            ], $attributes))->save();
        }
    }
}
