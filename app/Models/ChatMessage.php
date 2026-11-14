<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One message in a customer ↔ spa chat (customer chat, Nov 2026). */
class ChatMessage extends Model
{
    public const SIDE_CUSTOMER = 'customer';
    public const SIDE_SPA      = 'spa';

    /** Longest message, in characters (also checked in the browser). */
    public const MAX_LENGTH = 1000;

    protected $fillable = [
        'chat_conversation_id',
        'sender_side',
        'sender_user_id',
        'branch_id',
        'body',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id')->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }
}
