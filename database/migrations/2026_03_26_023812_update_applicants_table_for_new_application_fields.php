<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->renameColumn('role', 'position_applied');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->string('availability')->nullable()->after('position_applied');
            $table->string('source')->nullable()->after('availability');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn(['availability', 'source']);
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->renameColumn('position_applied', 'role');
        });
    }
};