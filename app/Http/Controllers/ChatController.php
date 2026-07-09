<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatMessage;
use App\Services\ContentFilterService;
use App\Services\IpHasher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    private const NICKNAME_KEY = 'chat_nickname';

    private const ADJECTIVES = [
        'Amber', 'Brave', 'Calm', 'Clever', 'Comet', 'Coral', 'Echo', 'Golden',
        'Jade', 'Lucky', 'Mellow', 'Nova', 'Quiet', 'River', 'Silver', 'Solar',
    ];

    private const NOUNS = [
        'Breeze', 'Cedar', 'Cloud', 'Falcon', 'Harbor', 'Lantern', 'Maple', 'Orbit',
        'Otter', 'Panda', 'Pebble', 'Quill', 'Raven', 'Tiger', 'Willow', 'Zephyr',
    ];

    public function index(Request $request): Response
    {
        return Inertia::render('Chat', [
            'messages' => $this->latestMessages(),
            'chatNickname' => $this->nicknameFor($request),
            'chatStats' => [
                'totalMessages' => ChatMessage::query()->count(),
                'messagesToday' => ChatMessage::query()->whereDate('sent_at', today())->count(),
                'pollLabel' => 'Every 4 sec',
            ],
            'recentChatNicknames' => ChatMessage::query()
                ->newestFirst()
                ->limit(30)
                ->get(['nickname'])
                ->pluck('nickname')
                ->unique()
                ->take(8)
                ->values(),
        ]);
    }

    public function fetch(Request $request): JsonResponse
    {
        $afterId = max(0, (int) $request->query('after_id', 0));

        $messages = ChatMessage::query()
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'nickname', 'content', 'sent_at']);

        return response()->json([
            'items' => $messages,
            'nickname' => $this->nicknameFor($request),
        ]);
    }

    public function store(
        StoreChatMessageRequest $request,
        ContentFilterService $contentFilter,
        IpHasher $ipHasher,
    ): JsonResponse {
        $content = trim($request->string('content'));

        if ($contentFilter->containsBlockedContent($content)) {
            throw ValidationException::withMessages([
                'content' => 'Your message contains content that is not allowed.',
            ]);
        }

        $message = ChatMessage::create([
            'nickname' => $this->nicknameFor($request),
            'content' => $content,
            'ip_hash' => $ipHasher->hash($request->ip()),
        ]);

        return response()->json([
            'item' => $message->only(['id', 'nickname', 'content', 'sent_at']),
            'nickname' => $message->nickname,
        ]);
    }

    private function latestMessages(): array
    {
        return ChatMessage::query()
            ->newestFirst()
            ->limit(80)
            ->get(['id', 'nickname', 'content', 'sent_at'])
            ->sortBy('id')
            ->values()
            ->all();
    }

    private function nicknameFor(Request $request): string
    {
        if ($request->session()->has(self::NICKNAME_KEY)) {
            return (string) $request->session()->get(self::NICKNAME_KEY);
        }

        $nickname = self::ADJECTIVES[array_rand(self::ADJECTIVES)]
            .self::NOUNS[array_rand(self::NOUNS)]
            .random_int(10, 99);

        $request->session()->put(self::NICKNAME_KEY, $nickname);

        return $nickname;
    }
}
