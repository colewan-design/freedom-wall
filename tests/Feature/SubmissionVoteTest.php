<?php

namespace Tests\Feature;

use App\Models\Submission;
use App\Models\SubmissionVote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionVoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_browser_can_upvote_and_downvote_an_approved_post(): void
    {
        $post = $this->approved();

        $this->postJson(route('submissions.vote', $post), ['value' => 1])
            ->assertOk()
            ->assertJson(['score' => 1, 'your_vote' => 1]);

        $this->postJson(route('submissions.vote', $post), ['value' => -1])
            ->assertOk()
            ->assertJson(['score' => -1, 'your_vote' => -1]);

        $this->assertSame(1, SubmissionVote::query()->count());
    }

    public function test_pressing_the_same_arrow_again_takes_the_vote_back(): void
    {
        $post = $this->approved();

        $this->postJson(route('submissions.vote', $post), ['value' => 1]);

        $this->postJson(route('submissions.vote', $post), ['value' => 1])
            ->assertOk()
            ->assertJson(['score' => 0, 'your_vote' => 0]);

        $this->assertSame(0, SubmissionVote::query()->count());
    }

    public function test_hammering_the_arrow_never_moves_the_score_past_one(): void
    {
        $post = $this->approved();

        // Twenty upvotes from one browser, no retracting pair in between.
        foreach (range(1, 20) as $ignored) {
            $this->postJson(route('submissions.vote', $post), ['value' => 1]);
            $this->postJson(route('submissions.vote', $post), ['value' => -1]);
            $this->postJson(route('submissions.vote', $post), ['value' => 1]);
        }

        $this->assertSame(1, (int) $post->votes()->sum('value'));
        $this->assertSame(1, SubmissionVote::query()->where('submission_id', $post->id)->count());
    }

    public function test_two_browsers_each_get_their_own_vote(): void
    {
        $post = $this->approved();

        $this->postJson(route('submissions.vote', $post), ['value' => 1]);

        // A second browser: nothing of the first one's session carries over.
        $this->flushSession();
        $this->postJson(route('submissions.vote', $post), ['value' => 1]);

        $this->assertSame(2, SubmissionVote::query()->where('submission_id', $post->id)->count());
        $this->assertSame(2, (int) $post->votes()->sum('value'));
    }

    public function test_the_cookie_alone_holds_the_vote_identity(): void
    {
        $post = $this->approved();

        // No shared session between these two calls, so the only thing saying
        // "same browser" is the cookie — which is the point of it outliving
        // the session window. withCredentials is what makes the test client
        // send cookies on a JSON request at all; a browser always does.
        $this->withCredentials()->withCookie('fw_voter', 'one-browser');

        $this->postJson(route('submissions.vote', $post), ['value' => 1])
            ->assertJson(['score' => 1, 'your_vote' => 1]);

        $this->flushSession();

        $this->postJson(route('submissions.vote', $post), ['value' => 1])
            ->assertJson(['score' => 0, 'your_vote' => 0]);

        $this->assertSame(0, SubmissionVote::query()->count());
    }

    public function test_the_wall_ships_each_post_score_and_the_visitors_own_vote(): void
    {
        // The wall orders by reviewed_at, so pin both to make posts.0 the one
        // being voted on rather than whichever the database hands back first.
        $post = $this->approved(['reviewed_at' => now()]);
        $this->approved(['reviewed_at' => now()->subHour()]);

        $this->postJson(route('submissions.vote', $post), ['value' => 1]);

        $this->get(route('wall'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('posts.0.score', 1)
                ->where('posts.0.your_vote', 1)
                ->where('posts.1.score', 0)
                ->where('posts.1.your_vote', 0));
    }

    public function test_a_post_that_is_not_approved_cannot_be_voted_on(): void
    {
        $pending = $this->approved(['status' => 'pending', 'reviewed_at' => null]);

        $this->postJson(route('submissions.vote', $pending), ['value' => 1])->assertNotFound();

        $this->assertSame(0, SubmissionVote::query()->count());
    }

    public function test_only_plus_and_minus_one_are_accepted(): void
    {
        $post = $this->approved();

        foreach ([0, 5, -3, 2] as $value) {
            $this->postJson(route('submissions.vote', $post), ['value' => $value])
                ->assertStatus(422);
        }

        $this->assertSame(0, SubmissionVote::query()->count());
    }

    private function approved(array $attributes = []): Submission
    {
        $submission = new Submission;

        $submission->forceFill(array_merge([
            'content' => 'A post to vote on. #rant',
            'status' => 'approved',
            'ip_hash' => 'test-hash',
            'submitted_at' => now(),
            'reviewed_at' => now()->subMinutes(SubmissionVote::query()->count() + 1),
        ], $attributes))->save();

        return $submission;
    }
}
