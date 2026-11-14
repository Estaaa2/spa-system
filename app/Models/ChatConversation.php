<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One customer's chat with one spa (customer chat, Nov 2026). Spa-wide, not
 * per branch: every branch's page leads here. Read state is the last message
 * id each side has seen.
 */
class ChatConversation extends Model
{
    protected $fillable = [
        'spa_id',
        'customer_user_id',
        'last_message_at',
        'customer_read_id',
        'spa_read_id',
    ];

    protected $casts = [
        'last_message_at'  => 'datetime',
        'customer_read_id' => 'integer',
        'spa_read_id'      => 'integer',
    ];

    public function spa(): BelongsTo
    {
        return $this->belongsTo(Spa::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id')->withTrashed();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    /** Adds unread_count for one side: messages from the other side past its read mark. */
    public function scopeWithUnreadFor(Builder $query, string $side): Builder
    {
        $other = $side === ChatMessage::SIDE_CUSTOMER ? ChatMessage::SIDE_SPA : ChatMessage::SIDE_CUSTOMER;
        $readColumn = $side === ChatMessage::SIDE_CUSTOMER ? 'customer_read_id' : 'spa_read_id';

        return $query->withCount(['messages as unread_count' => fn ($m) => $m
            ->where('sender_side', $other)
            ->whereColumn('chat_messages.id', '>', 'chat_conversations.'.$readColumn)]);
    }
}
