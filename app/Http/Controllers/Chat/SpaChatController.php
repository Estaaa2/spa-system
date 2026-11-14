<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Spa side of the chat: the shared inbox behind the floating button on staff
 * pages (customer chat, Nov 2026), polled by resources/js/staff-chat.js.
 * Staff of the spa with "reply to customer messages" only.
 *
 * GET  /spa-chat/unread                              badge count
 * GET  /spa-chat/conversations                       inbox, newest first
 * GET  /spa-chat/conversations/{conversation}        thread (?after=id); marks read
 * POST /spa-chat/conversations/{conversation}        reply {body}
 */
class SpaChatController extends Controller
{
    public function __construct(private ChatService $chat)
    {
    }

    public function unread(Request $request): JsonResponse
    {
        $this->authorizeInbox($request);

        $unread = ChatConversation::where('spa_id', $request->user()->spa_id)
            ->withUnreadFor(ChatMessage::SIDE_SPA)
            ->get(['id', 'spa_read_id'])
            ->sum('unread_count');

        return response()->json(['unread' => (int) $unread]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $this->authorizeInbox($request);

        $rows = ChatConversation::query()
            ->where('spa_id', $request->user()->spa_id)
            ->whereNotNull('last_message_at')
            ->withUnreadFor(ChatMessage::SIDE_SPA)
            ->with(['customer:id,first_name,middle_name,last_name,suffix', 'latestMessage.branch'])
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();

        $list = $rows->map(fn (ChatConversation $c) => [
            'id'       => $c->id,
            'customer' => $c->customer?->name ?? 'Customer',
            'initial'  => Str::upper(Str::substr($c->customer?->name ?? 'C', 0, 1)),
            'branch'   => $c->latestMessage?->branch?->name,
            'preview'  => Str::limit((string) $c->latestMessage?->body, 80),
            'from_me'  => $c->latestMessage?->sender_side === ChatMessage::SIDE_SPA,
            'time'     => $c->last_message_at?->isToday()
                ? $c->last_message_at->format('g:i A')
                : $c->last_message_at?->format('M j'),
            'unread'   => (int) $c->unread_count,
        ]);

        return response()->json([
            'conversations' => $list,
            'unread'        => $list->sum('unread'),
        ]);
    }

    public function thread(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeThread($request, $conversation);

        $after = max((int) $request->query('after', 0), 0);
        $messages = $this->chat->messages($conversation, $after);

        if ($messages->isNotEmpty()) {
            $this->chat->markRead($conversation, ChatMessage::SIDE_SPA);
        }

        return response()->json([
            'customer' => $conversation->customer?->name ?? 'Customer',
            'messages' => $messages->map(fn ($m) => $this->chat->present($m, ChatMessage::SIDE_SPA))->values(),
        ]);
    }

    public function send(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeThread($request, $conversation);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.ChatMessage::MAX_LENGTH],
        ], [
            'body.required' => 'Type a message first.',
            'body.max'      => 'Keep it under '.ChatMessage::MAX_LENGTH.' characters.',
        ]);

        $body = trim($data['body']);
        if ($body === '') {
            return response()->json(['message' => 'Type a message first.'], 422);
        }

        $message = $this->chat->send($conversation, $request->user(), ChatMessage::SIDE_SPA, $body);

        return response()->json([
            'message' => $this->chat->present($message->load('sender'), ChatMessage::SIDE_SPA),
        ], 201);
    }

    private function authorizeInbox(Request $request): void
    {
        abort_unless($this->chat->staffCanReply($request->user()), 403);
    }

    private function authorizeThread(Request $request, ChatConversation $conversation): void
    {
        // Another spa's thread: answer as if it doesn't exist.
        abort_unless($this->chat->staffOwns($request->user(), $conversation), 404);
    }
}
