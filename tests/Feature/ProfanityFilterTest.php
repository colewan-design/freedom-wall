<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every place a student can write something public goes through
 * ContentFilterService. These cover the wiring; the matching itself is
 * exercised in tests/Unit/ContentFilterServiceTest.php.
 */
class ProfanityFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_a_wall_submission(): void
    {
        $response = $this->post('/submissions', [
            'content' => 'Tangina ng prof ko sa major subject.',
            'category' => 'confessions',
            'captchaToken' => 'test-token',
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, Submission::query()->count());
    }

    public function test_it_rejects_a_wall_hashtag(): void
    {
        $response = $this->post('/submissions', [
            'content' => 'Nothing wrong with this body.',
            'category' => 'putangina',
            'captchaToken' => 'test-token',
        ]);

        $response->assertSessionHasErrors('category');
        $this->assertSame(0, Submission::query()->count());
    }

    public function test_it_rejects_a_global_chat_message(): void
    {
        $response = $this->postJson(route('chat.messages.store'), [
            'content' => 'gago kayong lahat',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('content');
        $this->assertSame(0, ChatMessage::query()->count());
    }

    public function test_it_rejects_a_chat_message_written_around_the_filter(): void
    {
        $response = $this->postJson(route('chat.messages.store'), [
            'content' => 'p u t @ n g i n a',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, ChatMessage::query()->count());
    }

    public function test_it_still_accepts_an_ordinary_chat_message(): void
    {
        $response = $this->postJson(route('chat.messages.store'), [
            'content' => 'Sino may kopya ng reviewer sa finals?',
        ]);

        $response->assertOk();
        $this->assertSame(1, ChatMessage::query()->count());
    }

    public function test_it_rejects_a_feed_post(): void
    {
        $response = $this
            ->actingAs(User::factory()->create())
            ->post(route('posts.store'), [
                'content' => 'Bobo ng kaklase ko sa group work.',
            ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, Post::query()->count());
    }

    public function test_it_rejects_a_comment(): void
    {
        $user = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => $user->id,
            'content' => 'Anyone up for a study session?',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('posts.comments.store', $post), [
                'content' => 'shut up you fucking idiot',
            ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, Comment::query()->count());
    }
}
