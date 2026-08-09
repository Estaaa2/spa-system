<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reassignment_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('leave_request_id')->nullable()->after('id');
            $table->foreign('leave_request_id')->references('id')->on('leave_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reassignment_requests', function (Blueprint $table) {
            $table->dropForeign(['leave_request_id']);
            $table->dropColumn('leave_request_id');
        });
    }
};