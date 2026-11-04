<?php

namespace Tests\Feature;

use App\Models\Submission;
use App\Models\SubmissionVote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WallLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_board_ranks_posts_by_score(): void
    {
        $best = $this->approved('Best one. #kilig', now());
        $middle = $this->approved('Second. #rant', now());
        $worst = $this->approved('Third. #question', now());

        $this->votes($best, 3);
        $this->votes($middle, 2);
        $this->votes($worst, 1);

        $this->get(route('wall'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('postBoard.all.0.id', $best->id)
                ->where('postBoard.all.0.score', 3)
                ->where('postBoard.all.1.id', $middle->id)
                ->where('postBoard.all.2.id', $worst->id)
                ->where('voteTotal', 6));
    }

    public function test_posts_on_zero_or_below_stay_off_the_board(): void
    {
        $up = $this->approved('Liked. #kilig', now());
        $down = $this->approved('Disliked. #rant', now());
        $this->approved('Untouched. #question', now());

        $this->votes($up, 1);
        $this->votes($down, 1, -1);

        $this->get(route('wall'))
            ->assertInertia(fn ($page) => $page
                ->has('postBoard.all', 1)
                ->where('postBoard.all.0.id', $up->id));
    }

    public function test_each_window_only_counts_posts_put_up_inside_it(): void
    {
        $today = $this->approved('Today. #kilig', now());
        $thisWeek = $this->approved('Three days back. #rant', now()->subDays(3));
        $older = $this->approved('Forty days back. #question', now()->subDays(40));

        $this->votes($today, 1);
        $this->votes($thisWeek, 2);
        $this->votes($older, 5);

        $this->get(route('wall'))
            ->assertInertia(fn ($page) => $page
                ->has('postBoard.today', 1)
                ->where('postBoard.today.0.id', $today->id)
                ->has('postBoard.week', 2)
                ->where('postBoard.week.0.id', $thisWeek->id)
                ->has('postBoard.month', 2)
                // Only all-time reaches back far enough for the oldest one,
                // which outscores everything.
                ->has('postBoard.all', 3)
                ->where('postBoard.all.0.id', $older->id));
    }

    public function test_the_board_carries_the_visitors_own_vote(): void
    {
        $post = $this->approved('Voted on by this browser. #kilig', now());
        $this->votes($post, 2);

        $this->postJson(route('submissions.vote', $post), ['value' => 1]);

        $this->get(route('wall'))
            ->assertInertia(fn ($page) => $page
                ->where('postBoard.all.0.score', 3)
                ->where('postBoard.all.0.your_vote', 1));
    }

    public function test_the_board_tops_out_at_six_rows(): void
    {
        foreach (range(1, 9) as $index) {
            $post = $this->approved("Post {$index}. #rant", now()->subMinutes($index));
            $this->votes($post, $index);
        }

        $this->get(route('wall'))
            ->assertInertia(fn ($page) => $page->has('postBoard.all', 6));
    }

    /** Cast $count upvotes (or downvotes) from that many distinct browsers. */
    private function votes(Submission $post, int $count, int $value = 1): void
    {
        foreach (range(1, $count) as $index) {
            SubmissionVote::create([
                'submission_id' => $post->id,
                'voter_token' => "voter-{$post->id}-{$index}",
                'value' => $value,
            ]);
        }
    }

    private function approved(string $content, \DateTimeInterface $reviewedAt): Submission
    {
        $submission = new Submission;

        $submission->forceFill([
            'content' => $content,
            'status' => 'approved',
            'ip_hash' => 'test-hash',
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $reviewedAt,
        ])->save();

        return $submission;
    }
}
