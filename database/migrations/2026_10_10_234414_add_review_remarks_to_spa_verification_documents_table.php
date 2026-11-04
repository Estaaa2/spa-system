<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_verification_documents', function (Blueprint $table) {
            // Filled when the administrator sends back one document of an
            // already-verified spa or branch. Cleared on the next upload.
            $table->text('review_remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('spa_verification_documents', function (Blueprint $table) {
            $table->dropColumn('review_remarks');
        });
    }
};