<?php

namespace Tests\Feature;

use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WallHashtagCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sidebar_directory_counts_posts_per_hashtag(): void
    {
        $this->approved('Nothing beats a quiet library. #confessions', now());
        $this->approved('Second one today. #confessions', now()->subHours(2));
        $this->approved('Parking again. #rant', now()->subDays(3));
        $this->approved('Saw them in the hallway. #kilig', now()->subDays(40));

        $this->get(route('wall'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tagCounts', [
                    ['tag' => 'confessions', 'count' => 2],
                    ['tag' => 'kilig', 'count' => 1],
                    ['tag' => 'rant', 'count' => 1],
                ]));
    }

    public function test_only_approved_posts_and_only_the_trailing_hashtag_are_counted(): void
    {
        $this->approved('Approved and tagged. #rant', now());
        $this->approved('Still in the queue. #rant', now(), ['status' => 'pending', 'reviewed_at' => null]);
        $this->approved('Turned down. #rant', now(), ['status' => 'rejected']);
        // The hashtag is mid-sentence, so it is the author's own word rather
        // than the tag the post was filed under.
        $this->approved('My #rant about the queue goes here.', now());

        $this->get(route('wall'))
            ->assertInertia(fn ($page) => $page
                ->where('tagCounts', [['tag' => 'rant', 'count' => 1]]));
    }

    public function test_ties_are_broken_alphabetically_so_the_order_holds_between_requests(): void
    {
        $this->approved('One. #rant', now());
        $this->approved('Two. #announcement', now());
        $this->approved('Three. #kilig', now());

        $this->get(route('wall'))
            ->assertInertia(fn ($page) => $page
                ->where('tagCounts', [
                    ['tag' => 'announcement', 'count' => 1],
                    ['tag' => 'kilig', 'count' => 1],
                    ['tag' => 'rant', 'count' => 1],
                ]));
    }

    private function approved(string $content, \DateTimeInterface $reviewedAt, array $attributes = []): Submission
    {
        $submission = new Submission;

        $submission->forceFill(array_merge([
            'content' => $content,
            'status' => 'approved',
            'ip_hash' => 'test-hash',
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $reviewedAt,
        ], $attributes))->save();

        return $submission;
    }
}
