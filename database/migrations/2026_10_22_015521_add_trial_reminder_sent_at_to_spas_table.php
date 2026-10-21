<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spas', function (Blueprint $table) {
            if (! Schema::hasColumn('spas', 'trial_reminder_sent_at')) {
                $table->timestamp('trial_reminder_sent_at')
                    ->nullable()
                    ->after('trial_used');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spas', function (Blueprint $table) {
            if (Schema::hasColumn('spas', 'trial_reminder_sent_at')) {
                $table->dropColumn('trial_reminder_sent_at');
            }
        });
    }
};