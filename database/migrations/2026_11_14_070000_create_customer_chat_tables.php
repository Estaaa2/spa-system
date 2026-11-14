<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer ↔ spa chat (Nov 2026). One conversation per customer per spa, not
 * per branch: a message sent from any branch's page lands in the same thread,
 * and staff at any branch of that spa (with "reply to customer messages") can
 * answer it. Each message keeps the branch page it was written from.
 *
 * Read state is a "last read message id" per side, so unread = messages from
 * the other side with a higher id. The spa side is one shared inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spa_id')->constrained('spas')->cascadeOnDelete();
            $table->foreignId('customer_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedBigInteger('customer_read_id')->default(0);
            $table->unsignedBigInteger('spa_read_id')->default(0);
            $table->timestamps();

            $table->unique(['spa_id', 'customer_user_id']);
            $table->index(['spa_id', 'last_message_at']);
            $table->index(['customer_user_id', 'last_message_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('sender_side', 10);                      // customer | spa
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete(); // page it came from
            $table->text('body');
            $table->timestamps();

            $table->index(['chat_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
