<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_a_chat_message_with_the_session_nickname(): void
    {
        $response = $this
            ->withSession(['chat_nickname' => 'CalmFalcon42'])
            ->postJson(route('chat.messages.store'), [
                'content' => 'Hello from the room.',
            ]);

        $response->assertOk();
        $response->assertJsonPath('item.nickname', 'CalmFalcon42');
        $response->assertJsonPath('item.content', 'Hello from the room.');

        $this->assertDatabaseHas('chat_messages', [
            'nickname' => 'CalmFalcon42',
            'content' => 'Hello from the room.',
        ]);
    }

    public function test_it_returns_messages_after_a_given_id(): void
    {
        ChatMessage::query()->create([
            'nickname' => 'AmberCloud12',
            'content' => 'Older message',
            'ip_hash' => 'hash-1',
        ]);

        $latest = ChatMessage::query()->create([
            'nickname' => 'NovaTiger44',
            'content' => 'Newest message',
            'ip_hash' => 'hash-2',
        ]);

        $response = $this->getJson(route('chat.messages.index', ['after_id' => $latest->id - 1]));

        $response->assertOk();
        $response->assertJsonCount(1, 'items');
        $response->assertJsonPath('items.0.content', 'Newest message');
    }

    public function test_it_updates_the_session_nickname(): void
    {
        $response = $this
            ->withSession(['chat_nickname' => 'CalmFalcon42'])
            ->postJson(route('chat.nickname.update'), [
                'nickname' => 'SilverOtter7',
            ]);

        $response->assertOk();
        $response->assertJsonPath('nickname', 'SilverOtter7');
        $this->assertSame('SilverOtter7', session('chat_nickname'));
    }

    public function test_it_rejects_a_nickname_with_symbols_or_spaces(): void
    {
        $response = $this
            ->withSession(['chat_nickname' => 'CalmFalcon42'])
            ->postJson(route('chat.nickname.update'), [
                'nickname' => 'silver otter!',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nickname');
        $this->assertSame('CalmFalcon42', session('chat_nickname'));
    }

    public function test_it_rejects_a_blocked_nickname(): void
    {
        $response = $this
            ->withSession(['chat_nickname' => 'CalmFalcon42'])
            ->postJson(route('chat.nickname.update'), [
                'nickname' => 'nigger',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nickname');
        $this->assertSame('CalmFalcon42', session('chat_nickname'));
    }

    public function test_it_lists_recently_active_members_once_each(): void
    {
        $this->messageSentAt('AmberCloud12', 'First', now()->subMinutes(9));
        $this->messageSentAt('AmberCloud12', 'Second', now()->subMinutes(2));
        $this->messageSentAt('NovaTiger44', 'Newest', now()->subMinute());

        // Outside the 15-minute presence window.
        $this->messageSentAt('QuietRiver33', 'Long gone', now()->subHour());

        $this->get(route('chat'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Chat')
                ->where('chatPresence.onlineCount', 2)
                ->has('chatPresence.members', 2)
                ->where('chatPresence.members.0.nickname', 'NovaTiger44')
                ->where('chatPresence.members.1.nickname', 'AmberCloud12')
            );
    }

    public function test_it_rejects_blocked_chat_content(): void
    {
        $response = $this
            ->withSession(['chat_nickname' => 'QuietRiver33'])
            ->postJson(route('chat.messages.store'), [
                'content' => 'This contains a slur: nigger',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('content');
    }

    /** sent_at is guarded, so backdate it after the insert. */
    private function messageSentAt(string $nickname, string $content, $sentAt): void
    {
        ChatMessage::query()
            ->create([
                'nickname' => $nickname,
                'content' => $content,
                'ip_hash' => 'hash-'.$nickname,
            ])
            ->forceFill(['sent_at' => $sentAt])
            ->save();
    }
}
