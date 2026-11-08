<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interview rejection: rejection_reason stays an internal HR note; this is the
 * optional note that is included in the "not selected" email to the applicant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            if (! Schema::hasColumn('interviews', 'applicant_message')) {
                $table->text('applicant_message')->nullable()->after('rejection_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            if (Schema::hasColumn('interviews', 'applicant_message')) {
                $table->dropColumn('applicant_message');
            }
        });
    }
};
