<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_branch_deployments', function (Blueprint $table) {
            $table->enum('staff_response', ['pending', 'accepted', 'declined'])
                ->default('pending')
                ->after('status')
                ->comment('Independent staff consent track — separate from Owner/HR status');

            $table->timestamp('staff_responded_at')->nullable()->after('staff_response');

            $table->text('staff_decline_reason')->nullable()->after('staff_responded_at');
        });
    }

    public function down(): void
    {
        Schema::table('staff_branch_deployments', function (Blueprint $table) {
            $table->dropColumn(['staff_response', 'staff_responded_at', 'staff_decline_reason']);
        });
    }
};
