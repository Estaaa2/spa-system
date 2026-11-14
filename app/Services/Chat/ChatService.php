<?php

namespace App\Services\Chat;

use App\Models\Branch;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Spa;
use App\Models\User;
use App\Services\Payroll\PayrollPermissions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Customer ↔ spa chat (Nov 2026). The rules live here; the two controllers
 * (customer and spa side) only shape requests and JSON.
 *
 *   One conversation per customer per spa. A customer writes from a branch's
 *   page; the message remembers that branch, but the thread is spa-wide.
 *   Spa side: staff of that spa with "reply to customer messages" (checked on
 *   their current branch, like every branch permission). One shared inbox.
 *   Customers see the spa's name on replies; staff see who on their team wrote.
 */
class ChatService
{
    public const PERMISSION = 'reply to customer messages';

    /** Messages sent per poll; older ones load on open. */
    public const PAGE = 50;

    public function staffCanReply(?User $user): bool
    {
        return $user !== null
            && $user->spa_id
            && ! $user->hasRole('customer')
            && $user->hasBranchPermission(self::PERMISSION);
    }

    public function staffOwns(User $user, ChatConversation $conversation): bool
    {
        return $this->staffCanReply($user) && (int) $user->spa_id === (int) $conversation->spa_id;
    }

    /** The spa a customer may message: verified, with the branch listed and belonging to it. */
    public function canMessage(Spa $spa, ?Branch $branch): bool
    {
        return $spa->verification_status === 'verified'
            && $branch !== null
            && (int) $branch->spa_id === (int) $spa->id
            && (bool) $branch->profile?->is_listed;
    }

    public function conversationFor(User $customer, Spa $spa): ?ChatConversation
    {
        return ChatConversation::where('spa_id', $spa->id)->where('customer_user_id', $customer->id)->first();
    }

    public function send(ChatConversation|array $target, User $sender, string $side, string $body, ?int $branchId = null): ChatMessage
    {
        return DB::transaction(function () use ($target, $sender, $side, $body, $branchId) {
            $conversation = $target instanceof ChatConversation
                ? $target
                : ChatConversation::firstOrCreate($target);

            $message = $conversation->messages()->create([
                'sender_side'    => $side,
                'sender_user_id' => $sender->id,
                'branch_id'      => $branchId,
                'body'           => $body,
            ]);

            // Your own message counts as read by your side.
            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                $side === ChatMessage::SIDE_CUSTOMER ? 'customer_read_id' : 'spa_read_id' => $message->id,
            ])->save();

            return $message;
        });
    }

    /** Messages after $afterId (oldest first), the latest PAGE when $afterId is 0. */
    public function messages(ChatConversation $conversation, int $afterId = 0): Collection
    {
        $query = $conversation->messages()->with(['sender', 'branch']);

        if ($afterId > 0) {
            return $query->where('id', '>', $afterId)->orderBy('id')->limit(self::PAGE)->get();
        }

        return $query->orderByDesc('id')->limit(self::PAGE)->get()->reverse()->values();
    }

    public function markRead(ChatConversation $conversation, string $side): void
    {
        $column = $side === ChatMessage::SIDE_CUSTOMER ? 'customer_read_id' : 'spa_read_id';
        $latest = (int) $conversation->messages()->max('id');

        if ($latest > (int) $conversation->{$column}) {
            $conversation->forceFill([$column => $latest])->save();
        }
    }

    /** One message as JSON for $viewerSide. */
    public function present(ChatMessage $m, string $viewerSide, ?string $spaName = null): array
    {
        $mine = $m->sender_side === $viewerSide;

        if ($m->sender_side === ChatMessage::SIDE_SPA) {
            // Customers see the spa; staff see which teammate answered.
            $from = $viewerSide === ChatMessage::SIDE_SPA
                ? trim(($m->sender?->first_name ?? 'Staff').' · '.PayrollPermissions::roleLabel($m->sender?->getRoleNames()->first()))
                : ($spaName ?? 'Spa');
        } else {
            $from = $m->sender?->name ?? 'Customer';
        }

        return [
            'id'     => $m->id,
            'mine'   => $mine,
            'from'   => $from,
            'body'   => $m->body,
            'branch' => $viewerSide === ChatMessage::SIDE_SPA && $m->sender_side === ChatMessage::SIDE_CUSTOMER
                ? $m->branch?->name
                : null,
            'time'   => $m->created_at->format('g:i A'),
            'date'   => $m->created_at->format('M j, Y'),
            'at'     => $m->created_at->toIso8601String(),
        ];
    }
}
