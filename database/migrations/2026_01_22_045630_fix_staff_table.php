<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('staff', function (Blueprint $table) {
            // Add unique constraint to phone (if not already)
            $table->string('phone')->unique()->change();

            // Add specialization if not exists
            if (!Schema::hasColumn('staff', 'specialization')) {
                $table->string('specialization')->nullable()->after('roles');
            }

            // Add branch_id if not exists
            if (!Schema::hasColumn('staff', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('specialization')->constrained()->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('specialization');
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
