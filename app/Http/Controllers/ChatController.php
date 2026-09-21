<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatMessageRequest;
use App\Http\Requests\UpdateChatNicknameRequest;
use App\Models\ChatMessage;
use App\Services\ContentFilterService;
use App\Services\IpHasher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    private const NICKNAME_KEY = 'chat_nickname';

    /** How recently a nickname must have posted to count as still in the room. */
    private const ONLINE_WINDOW_MINUTES = 15;

    private const ACTIVE_MEMBER_LIMIT = 8;

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
            'chatPresence' => $this->presence(),
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
            'presence' => $this->presence(),
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

    public function updateNickname(
        UpdateChatNicknameRequest $request,
        ContentFilterService $contentFilter,
    ): JsonResponse {
        $nickname = trim($request->string('nickname'));

        if ($contentFilter->containsBlockedContent($nickname)) {
            throw ValidationException::withMessages([
                'nickname' => 'That nickname contains content that is not allowed.',
            ]);
        }

        $request->session()->put(self::NICKNAME_KEY, $nickname);

        return response()->json(['nickname' => $nickname]);
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

    /**
     * Who is still in the room, newest activity first. The room has no
     * connection state, so "online" is inferred from recent message times.
     */
    private function presence(): array
    {
        $since = now()->subMinutes(self::ONLINE_WINDOW_MINUTES);

        $members = ChatMessage::query()
            ->where('sent_at', '>=', $since)
            ->groupBy('nickname')
            ->orderByDesc('last_sent_at')
            ->limit(self::ACTIVE_MEMBER_LIMIT)
            ->selectRaw('nickname, MAX(sent_at) as last_sent_at')
            ->get()
            ->map(fn (ChatMessage $row) => [
                'nickname' => $row->nickname,
                'lastSentAt' => Carbon::parse($row->last_sent_at)->toJSON(),
            ])
            ->all();

        return [
            'members' => $members,
            'onlineCount' => ChatMessage::query()
                ->where('sent_at', '>=', $since)
                ->distinct()
                ->count('nickname'),
            'windowMinutes' => self::ONLINE_WINDOW_MINUTES,
        ];
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
