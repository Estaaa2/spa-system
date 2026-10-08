<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First change the old ENUM to a string so MySQL accepts
        // the new premium and business plan values.
        Schema::table('spas', function (Blueprint $table) {
            $table->string('business_tier')
                ->default('basic')
                ->change();
        });

        DB::table('spas')
            ->where('business_tier', 'professional')
            ->update([
                'business_tier' => 'premium',
            ]);

        DB::table('subscriptions')
            ->where('business_tier', 'professional')
            ->update([
                'business_tier' => 'premium',
            ]);
    }

    public function down(): void
    {
        // Convert premium back before restoring the old ENUM.
        DB::table('spas')
            ->where('business_tier', 'premium')
            ->update([
                'business_tier' => 'professional',
            ]);

        DB::table('subscriptions')
            ->where('business_tier', 'premium')
            ->update([
                'business_tier' => 'professional',
            ]);

        Schema::table('spas', function (Blueprint $table) {
            $table->enum('business_tier', ['basic', 'professional'])
                ->default('basic')
                ->change();
        });
    }
};