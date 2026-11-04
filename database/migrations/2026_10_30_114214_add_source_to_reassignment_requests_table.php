<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a reassignment request exists. NULL for rows made before this column
 * (therapist "Can't make it", or leave — those have leave_request_id).
 *
 * unverified  the assigned therapist stopped being identity-verified, and the
 *             booking is online home service (staff verification, decision Q33)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reassignment_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('reassignment_requests', 'source')) {
                $table->string('source', 20)->nullable()->after('reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reassignment_requests', function (Blueprint $table) {
            if (Schema::hasColumn('reassignment_requests', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
