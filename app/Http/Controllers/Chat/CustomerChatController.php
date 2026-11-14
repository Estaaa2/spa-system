<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Spa;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Customer side of the chat (customer chat, Nov 2026), polled by
 * resources/js/customer-chat.js.
 *
 * GET  /chat/conversations             the spas you messaged + unread counts
 * GET  /chat/spas/{spa}/messages       the thread (?after=id for new ones); marks read
 * POST /chat/spas/{spa}/messages       send {body, branch_id}
 */
class CustomerChatController extends Controller
{
    public function __construct(private ChatService $chat)
    {
    }

    public function conversations(Request $request): JsonResponse
    {
        $rows = ChatConversation::query()
            ->where('customer_user_id', $request->user()->id)
            ->whereNotNull('last_message_at')
            ->withUnreadFor(ChatMessage::SIDE_CUSTOMER)
            ->with(['spa:id,name', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get();

        // The branch page each thread was last written from, so reopening it
        // from the floating button sends from the same branch.
        $lastBranch = ChatMessage::query()
            ->whereIn('chat_conversation_id', $rows->pluck('id'))
            ->where('sender_side', ChatMessage::SIDE_CUSTOMER)
            ->whereNotNull('branch_id')
            ->selectRaw('chat_conversation_id, MAX(id) as id')
            ->groupBy('chat_conversation_id')
            ->pluck('id', 'chat_conversation_id');
        $branchOf = ChatMessage::whereIn('id', $lastBranch->values())->pluck('branch_id', 'chat_conversation_id');

        $list = $rows->map(fn (ChatConversation $c) => [
            'spa_id'    => $c->spa_id,
            'spa_name'  => $c->spa?->name ?? 'Spa',
            'branch_id' => $branchOf[$c->id] ?? null,
            'preview'   => Str::limit((string) $c->latestMessage?->body, 80),
            'from_me'   => $c->latestMessage?->sender_side === ChatMessage::SIDE_CUSTOMER,
            'time'      => $this->when($c->last_message_at),
            'unread'    => (int) $c->unread_count,
        ]);

        return response()->json([
            'conversations' => $list,
            'unread'        => $list->sum('unread'),
        ]);
    }

    public function thread(Request $request, Spa $spa): JsonResponse
    {
        $conversation = $this->chat->conversationFor($request->user(), $spa);
        $after = max((int) $request->query('after', 0), 0);
        $messages = $conversation ? $this->chat->messages($conversation, $after) : collect();

        if ($conversation && $messages->isNotEmpty()) {
            $this->chat->markRead($conversation, ChatMessage::SIDE_CUSTOMER);
        }

        return response()->json([
            'spa_name' => $spa->name,
            'messages' => $messages->map(fn ($m) => $this->chat->present($m, ChatMessage::SIDE_CUSTOMER, $spa->name))->values(),
        ]);
    }

    public function send(Request $request, Spa $spa): JsonResponse
    {
        $data = $request->validate([
            'body'      => ['required', 'string', 'max:'.ChatMessage::MAX_LENGTH],
            'branch_id' => ['required', 'integer'],
        ], [
            'body.required' => 'Type a message first.',
            'body.max'      => 'Keep it under '.ChatMessage::MAX_LENGTH.' characters.',
        ]);

        $body = trim($data['body']);
        if ($body === '') {
            return response()->json(['message' => 'Type a message first.'], 422);
        }

        $branch = Branch::with('profile')->find($data['branch_id']);
        if (! $this->chat->canMessage($spa, $branch)) {
            return response()->json(['message' => 'This spa can\'t receive messages right now.'], 422);
        }

        $message = $this->chat->send(
            ['spa_id' => $spa->id, 'customer_user_id' => $request->user()->id],
            $request->user(),
            ChatMessage::SIDE_CUSTOMER,
            $body,
            $branch->id,
        );

        return response()->json([
            'message' => $this->chat->present($message->load(['sender', 'branch']), ChatMessage::SIDE_CUSTOMER, $spa->name),
        ], 201);
    }

    private function when($at): ?string
    {
        if (! $at) {
            return null;
        }

        return $at->isToday() ? $at->format('g:i A') : $at->format('M j');
    }
}
