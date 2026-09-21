<?php

namespace Tests\Feature;

use App\Models\Thread;
use App\Models\ThreadReply;
use App\Models\ThreadReplyVote;
use App\Models\ThreadReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ThreadControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_opens_the_most_recently_active_thread(): void
    {
        $older = $this->makeThread(['title' => 'Older question']);
        $older->forceFill(['last_activity_at' => now()->subDay()])->save();

        $newer = $this->makeThread(['title' => 'Fresh question']);

        $this->get(route('threads.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Threads/Index')
                ->where('thread.id', $newer->id)
                ->has('threads', 2));
    }

    public function test_it_stores_a_thread_and_redirects_to_it(): void
    {
        $response = $this->post(route('threads.store'), [
            'title' => 'How do you manage heavy course load?',
            'body' => 'Midterms are coming up and I am drowning in requirements.',
            'topic' => '#Advice',
        ]);

        $thread = Thread::query()->sole();

        $response->assertRedirect(route('threads.show', $thread));

        // The tag is settled on one spelling before it is stored, so the topic
        // filter does not end up with #Advice next to #advice.
        $this->assertSame('advice', $thread->topic);
        $this->assertSame('visible', $thread->status);
    }

    public function test_it_rejects_a_thread_whose_title_is_blocked(): void
    {
        $response = $this->post(route('threads.store'), [
            'title' => 'Tangina ng prof ko',
            'body' => 'Nothing wrong with this body.',
            'topic' => 'rant',
        ]);

        $response->assertSessionHasErrors('title');
        $this->assertSame(0, Thread::query()->count());
    }

    public function test_it_rejects_a_thread_whose_body_is_blocked(): void
    {
        $response = $this->post(route('threads.store'), [
            'title' => 'A perfectly fine title',
            'body' => 'Tangina ng prof ko sa major subject.',
            'topic' => 'rant',
        ]);

        $response->assertSessionHasErrors('body');
        $this->assertSame(0, Thread::query()->count());
    }

    public function test_it_nests_a_reply_under_its_parent(): void
    {
        $thread = $this->makeThread();
        $parent = $this->makeReply($thread);

        $response = $this->postJson(route('threads.replies.store', $thread), [
            'body' => 'Time blocking really helps.',
            'parent_id' => $parent->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('item.parent_id', $parent->id);
        $response->assertJsonPath('item.depth', 1);
        $response->assertJsonPath('replyCount', 2);
    }

    public function test_a_reply_past_the_indent_cap_becomes_a_sibling(): void
    {
        $thread = $this->makeThread();
        $root = $this->makeReply($thread);
        $second = $this->makeReply($thread, ['parent_id' => $root->id, 'depth' => 1]);
        $deepest = $this->makeReply($thread, ['parent_id' => $second->id, 'depth' => ThreadReply::MAX_DEPTH]);

        $response = $this->postJson(route('threads.replies.store', $thread), [
            'body' => 'Answering the deepest one.',
            'parent_id' => $deepest->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('item.depth', ThreadReply::MAX_DEPTH);
        $response->assertJsonPath('item.parent_id', $second->id);
    }

    public function test_replying_bumps_the_thread_up_the_list(): void
    {
        $thread = $this->makeThread();
        $thread->forceFill(['last_activity_at' => now()->subWeek()])->save();

        $this->postJson(route('threads.replies.store', $thread), ['body' => 'Still relevant.'])
            ->assertOk();

        $this->assertTrue($thread->fresh()->last_activity_at->isToday());
    }

    public function test_a_second_vote_from_the_same_visitor_replaces_the_first(): void
    {
        $thread = $this->makeThread();
        $reply = $this->makeReply($thread);

        $this->postJson(route('threads.replies.vote', $reply), ['value' => 1])
            ->assertOk()
            ->assertJsonPath('score', 1);

        $this->postJson(route('threads.replies.vote', $reply), ['value' => -1])
            ->assertOk()
            ->assertJsonPath('score', -1)
            ->assertJsonPath('your_vote', -1);

        $this->assertSame(1, ThreadReplyVote::query()->count());
    }

    public function test_pressing_the_same_arrow_twice_clears_the_vote(): void
    {
        $thread = $this->makeThread();
        $reply = $this->makeReply($thread);

        $this->postJson(route('threads.replies.vote', $reply), ['value' => 1])->assertOk();

        $this->postJson(route('threads.replies.vote', $reply), ['value' => 1])
            ->assertOk()
            ->assertJsonPath('score', 0)
            ->assertJsonPath('your_vote', 0);

        $this->assertSame(0, ThreadReplyVote::query()->count());
    }

    public function test_a_view_is_counted_once_per_session(): void
    {
        $thread = $this->makeThread();

        $this->get(route('threads.show', $thread))->assertOk();
        $this->get(route('threads.show', $thread))->assertOk();

        $this->assertSame(1, $thread->fresh()->views_count);
    }

    public function test_a_hidden_thread_is_gone_from_the_page(): void
    {
        $thread = $this->makeThread();
        $thread->forceFill(['status' => 'hidden'])->save();

        $this->get(route('threads.show', $thread))->assertNotFound();

        $this->get(route('threads.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('thread', null)->has('threads', 0));
    }

    public function test_a_taken_down_reply_keeps_the_answers_under_it(): void
    {
        $thread = $this->makeThread();
        $parent = $this->makeReply($thread, ['body' => 'Taken down']);
        $this->makeReply($thread, ['parent_id' => $parent->id, 'depth' => 1, 'body' => 'Answer to it']);

        $parent->forceFill(['status' => 'hidden'])->save();

        $this->get(route('threads.show', $thread))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // The marker stands in for the hidden reply so the answer below
                // it does not silently drop off the page along with its parent.
                ->has('thread.replies', 1)
                ->where('thread.replies.0.removed', true)
                ->where('thread.replies.0.body', null)
                ->has('thread.replies.0.children', 1)
                ->where('thread.replies.0.children.0.body', 'Answer to it')
                ->where('thread.reply_count', 1));
    }

    public function test_a_taken_down_reply_with_nothing_under_it_is_gone_entirely(): void
    {
        $thread = $this->makeThread();
        $this->makeReply($thread, ['body' => 'Kept']);
        $this->makeReply($thread, ['body' => 'Taken down'])->forceFill(['status' => 'hidden'])->save();

        $this->get(route('threads.show', $thread))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('thread.replies', 1)
                ->where('thread.replies.0.body', 'Kept'));
    }

    public function test_reporting_the_same_reply_twice_files_one_report(): void
    {
        $thread = $this->makeThread();
        $reply = $this->makeReply($thread);

        $this->postJson(route('threads.replies.report', $reply), ['reason' => 'harassment'])->assertOk();
        $this->postJson(route('threads.replies.report', $reply), ['reason' => 'spam'])->assertOk();

        $this->assertSame(1, ThreadReport::query()->count());
    }

    public function test_an_admin_hides_a_thread_and_closes_its_reports(): void
    {
        $thread = $this->makeThread();
        $this->postJson(route('threads.report', $thread), ['reason' => 'hate'])->assertOk();

        $this->actingAs($this->admin())
            ->postJson(route('admin.threads.hide', $thread))
            ->assertOk();

        $this->assertSame('hidden', $thread->fresh()->status);
        $this->assertSame(0, ThreadReport::query()->open()->count());
    }

    public function test_the_moderation_queue_is_closed_to_everyone_else(): void
    {
        $this->getJson(route('admin.threads.reports'))->assertUnauthorized();

        $this->actingAs(User::factory()->create(['role' => 'student']))
            ->getJson(route('admin.threads.reports'))
            ->assertForbidden();
    }

    private function makeThread(array $attributes = []): Thread
    {
        return Thread::create([
            'title' => 'How do you manage heavy course load?',
            'body' => 'Midterms are coming up and I am drowning in requirements.',
            'topic' => 'advice',
            'author_token' => 'author-'.uniqid(),
            'ip_hash' => 'hash',
            'last_activity_at' => now(),
            ...$attributes,
        ]);
    }

    private function makeReply(Thread $thread, array $attributes = []): ThreadReply
    {
        return ThreadReply::create([
            'thread_id' => $thread->id,
            'body' => 'Time blocking really helps.',
            'author_token' => 'author-'.uniqid(),
            'ip_hash' => 'hash',
            ...$attributes,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
