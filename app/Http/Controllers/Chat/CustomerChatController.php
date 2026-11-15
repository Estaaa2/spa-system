<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProfile;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Spa;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Customer side of the chat (customer chat, Nov 2026; per branch since Nov 15),
 * polled by resources/js/chat-widget.js and by the customer app through
 * /api/chat/* (customer mobile chat, Nov 15).
 *
 * GET  /chat/conversations                  the branches you messaged + unread counts
 * GET  /chat/branches/{branch}/messages     the thread (?after=id for new ones); marks read
 * POST /chat/branches/{branch}/messages     send {body}
 */
class CustomerChatController extends Controller
{
    public function __construct(private ChatService $chat)
    {
    }

    public function conversations(Request $request): JsonResponse
    {
        $forApp = $request->is('api/*');
        $rows = ChatConversation::query()
            ->where('customer_user_id', $request->user()->id)
            ->whereNotNull('branch_id')
            ->whereNotNull('last_message_at')
            ->withUnreadFor(ChatMessage::SIDE_CUSTOMER)
            ->with(['spa:id,name', 'branch:id,spa_id,name', 'branch.profile', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get();

        $list = $rows->map(fn (ChatConversation $c) => [
            'spa_id'       => $c->spa_id,
            'spa_name'     => $c->spa?->name ?? 'Spa',
            'branch_id'    => $c->branch_id,
            'branch_name'  => $c->branch?->name,
            'branch_label' => ChatService::branchLabel($c->branch?->name),
            // The app gets the PNG placeholder: Flutter can't draw the SVG one.
            'branch_photo' => $c->branch?->profile ? BranchProfile::thumbnailFor($c->branch->profile, $forApp) : null,
            'preview'      => Str::limit((string) $c->latestMessage?->body, 80),
            'from_me'      => $c->latestMessage?->sender_side === ChatMessage::SIDE_CUSTOMER,
            'time'         => $this->when($c->last_message_at),
            'unread'       => (int) $c->unread_count,
        ]);

        return response()->json([
            'conversations' => $list,
            'unread'        => $list->sum('unread'),
        ]);
    }

    public function thread(Request $request, Branch $branch): JsonResponse
    {
        $branch->loadMissing('profile');
        $spa = Spa::with('subscriptions')->find($branch->spa_id);
        $profile = $branch->profile;
        $conversation = $this->chat->conversationFor($request->user(), $branch);
        $after = max((int) $request->query('after', 0), 0);
        $messages = $conversation ? $this->chat->messages($conversation, $after) : collect();

        if ($conversation && $messages->isNotEmpty()) {
            $this->chat->markRead($conversation, ChatMessage::SIDE_CUSTOMER);
        }

        return response()->json([
            'spa_id'       => $spa?->id,
            'spa_name'     => $spa?->name ?? 'Spa',
            'branch_id'    => $branch->id,
            'branch_name'  => $branch->name,
            'branch_label' => ChatService::branchLabel($branch->name),
            // The branch bar (chat Option B) and decision Q7a: a branch the public
            // can't see keeps its history but takes no new messages.
            'branch_photo'   => $profile ? BranchProfile::thumbnailFor($profile, $request->is('api/*')) : null,
            'branch_address' => $this->addressSummary($profile?->address ?? $branch->location),
            'branch_public'  => $this->chat->canMessage($spa, $branch),
            // Suggested first messages for a brand-new chat (Option A). The web
            // widget gets them from the page; the app reads them here.
            'quick_questions' => ChatService::QUICK_QUESTIONS,
            'messages'     => $messages->map(fn ($m) => $this->chat->present($m, ChatMessage::SIDE_CUSTOMER, $spa?->name))->values(),
        ]);
    }

    public function send(Request $request, Branch $branch): JsonResponse
    {
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

        $branch->loadMissing('profile');
        $spa = Spa::with('subscriptions')->find($branch->spa_id);
        if (! $spa || ! $this->chat->canMessage($spa, $branch)) {
            return response()->json(['message' => 'This branch isn\'t accepting messages right now.'], 422);
        }

        $message = $this->chat->send(
            ['spa_id' => $spa->id, 'branch_id' => $branch->id, 'customer_user_id' => $request->user()->id],
            $request->user(),
            ChatMessage::SIDE_CUSTOMER,
            $body,
            $branch->id,
        );

        return response()->json([
            'message' => $this->chat->present($message->load(['sender', 'branch']), ChatMessage::SIDE_CUSTOMER, $spa->name),
        ], 201);
    }

    /** "Blk 1, Aguinaldo Hwy, Imus, Cavite, 4103, Philippines" → "Imus, Cavite", like the spa cards. */
    private function addressSummary(?string $address): ?string
    {
        $cleaned = preg_replace('/,?\s*(Philippines|Calabarzon|\d{4})\s*/i', '', (string) $address);
        $parts = array_values(array_filter(array_map('trim', explode(',', (string) $cleaned))));

        return $parts ? implode(', ', array_slice($parts, -2)) : null;
    }

    private function when($at): ?string
    {
        if (! $at) {
            return null;
        }

        return $at->isToday() ? $at->format('g:i A') : $at->format('M j');
    }
}
