<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ContentFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConversationMessageController extends Controller
{
    public function fetch(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless($conversation->hasParticipant($user), 403);

        $afterId = max(0, (int) $request->query('after_id', 0));

        $messages = $conversation->messages()
            ->with('user:id,name,username,avatar_path')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(50)
            ->get();

        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        return response()->json([
            'items' => $messages,
        ]);
    }

    public function store(
        Request $request,
        Conversation $conversation,
        ContentFilterService $contentFilter,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($conversation->hasParticipant($user), 403);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
        ]);

        $content = trim($validated['content']);

        if ($content === '') {
            throw ValidationException::withMessages([
                'content' => 'Write a message before sending.',
            ]);
        }

        if ($contentFilter->containsBlockedContent($content)) {
            throw ValidationException::withMessages([
                'content' => 'Your message contains content that is not allowed.',
            ]);
        }

        $message = new Message([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'content' => $content,
        ]);
        $message->created_at = now();
        $message->save();

        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        return response()->json([
            'item' => $message->load('user:id,name,username,avatar_path'),
        ]);
    }
}
