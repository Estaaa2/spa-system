<?php

namespace App\Services\Chat;

use App\Models\Branch;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Spa;
use App\Models\User;
use App\Services\Landing\PublicBranches;
use App\Services\Payroll\PayrollPermissions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Customer ↔ spa chat (Nov 2026). The rules live here; the two controllers
 * (customer and spa side) only shape requests and JSON.
 *
 *   One conversation per customer per branch (since Nov 15, 2026): messaging
 *   Imus and Bacoor gives two threads. Spa side: staff of that spa with "reply
 *   to customer messages" (checked on their current branch, like every branch
 *   permission). One shared inbox for all branches, each thread labelled with
 *   its branch; it does not follow the branch switcher.
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

    /**
     * A customer may message a branch only while the public can see it
     * (decision Q7a, Nov 15): the same rule as the landing page. Old threads
     * with a branch that is no longer public stay readable, but can't take
     * new messages.
     */
    public function canMessage(?Spa $spa, ?Branch $branch): bool
    {
        return app(PublicBranches::class)->isPublic($spa, $branch);
    }

    public function conversationFor(User $customer, Branch $branch): ?ChatConversation
    {
        return ChatConversation::where('branch_id', $branch->id)->where('customer_user_id', $customer->id)->first();
    }

    /** "Imus" → "Imus Branch". Kept here for the chat; the rule is PublicBranches::label(). */
    public static function branchLabel(?string $name): ?string
    {
        return PublicBranches::label($name);
    }

    public function send(ChatConversation|array $target, User $sender, string $side, string $body, ?int $branchId = null): ChatMessage
    {
        return DB::transaction(function () use ($target, $sender, $side, $body, $branchId) {
            // A new thread is keyed by branch; callers may pass only spa + customer.
            $conversation = $target instanceof ChatConversation
                ? $target
                : ChatConversation::firstOrCreate($target + ['branch_id' => $branchId]);

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
