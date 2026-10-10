<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_verification_documents', function (Blueprint $table) {
            // When the "expiring soon" email was last sent for this document.
            $table->timestamp('expiry_notice_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('spa_verification_documents', function (Blueprint $table) {
            $table->dropColumn('expiry_notice_sent_at');
        });
    }
};