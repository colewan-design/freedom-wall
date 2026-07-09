<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
