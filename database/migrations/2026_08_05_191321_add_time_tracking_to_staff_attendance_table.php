<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_attendance', function (Blueprint $table) {
            $table->time('time_in')->nullable()->after('status');
            $table->time('time_out')->nullable()->after('time_in');
            $table->unsignedBigInteger('marked_by')->nullable()->after('time_out');
            $table->enum('source', ['self', 'manual', 'system'])->default('manual')->after('marked_by');
            $table->boolean('auto_closed')->default(false)->after('source');

            $table->foreign('marked_by')->references('id')->on('users')->nullOnDelete();
        });

        // MySQL/MariaDB needs a raw statement to widen an existing ENUM column
        // (Schema::table()->enum() only works for adding a brand-new column).
        DB::statement("ALTER TABLE staff_attendance MODIFY COLUMN status ENUM('present','late','absent','on_leave') NOT NULL DEFAULT 'present'");
    }

    public function down(): void
    {
        Schema::table('staff_attendance', function (Blueprint $table) {
            $table->dropForeign(['marked_by']);
            $table->dropColumn(['time_in', 'time_out', 'marked_by', 'source', 'auto_closed']);
        });

        DB::statement("ALTER TABLE staff_attendance MODIFY COLUMN status ENUM('present','absent','late') NOT NULL DEFAULT 'present'");
    }
};