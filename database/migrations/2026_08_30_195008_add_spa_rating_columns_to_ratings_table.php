<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->unsignedTinyInteger('spa_rating')->nullable()->after('rating');
            $table->string('spa_comment', 500)->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropColumn(['spa_rating', 'spa_comment']);
        });
    }
};
