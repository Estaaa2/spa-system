<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer chat, per branch (Nov 15, 2026). A conversation now belongs to one
 * branch: a customer who messages Imus and Bacoor has two threads. The spa
 * side stays one shared inbox, each thread labelled with its branch.
 *
 * Existing threads (spa-wide until now) go to the branch of their latest
 * customer message (decision Q4a). Staff replies never stored a branch, so
 * splitting a thread by branch could not place them; it is not attempted.
 * A thread with no customer message from a branch falls back to the spa's
 * main branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('spa_id')
                ->constrained('branches')->nullOnDelete();
        });

        DB::table('chat_conversations')->orderBy('id')->each(function ($c) {
            $branchId = DB::table('chat_messages')
                ->where('chat_conversation_id', $c->id)
                ->where('sender_side', 'customer')
                ->whereNotNull('branch_id')
                ->orderByDesc('id')
                ->value('branch_id');

            $branchId ??= DB::table('branches')
                ->where('spa_id', $c->spa_id)
                ->orderByDesc('is_main')
                ->orderBy('id')
                ->value('id');

            DB::table('chat_conversations')->where('id', $c->id)->update(['branch_id' => $branchId]);
        });

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropUnique(['spa_id', 'customer_user_id']);
            $table->unique(['branch_id', 'customer_user_id']);
        });
    }

    public function down(): void
    {
        // Fails if a customer has threads with two branches of one spa; merge
        // or delete those first.
        Schema::table('chat_conversations', function (Blueprint $table) {
            // MySQL: the foreign key goes first, or the unique index it uses can't be dropped.
            $table->dropForeign(['branch_id']);
            $table->dropUnique(['branch_id', 'customer_user_id']);
            $table->unique(['spa_id', 'customer_user_id']);
            $table->dropColumn('branch_id');
        });
    }
};
