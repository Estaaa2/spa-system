<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Help guides Phase 1 (D4): when the user dismissed the "New here?" banner,
 * or opened Help & Guides. Null = the banner still shows. Every existing
 * account starts at null, so everyone sees the banner once (Q9 → A).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('guides_dismissed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('guides_dismissed_at');
        });
    }
};
