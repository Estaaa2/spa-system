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

        $this->setStatusValues(['present', 'late', 'absent', 'on_leave']);
    }

    public function down(): void
    {
        Schema::table('staff_attendance', function (Blueprint $table) {
            $table->dropForeign(['marked_by']);
            $table->dropColumn(['time_in', 'time_out', 'marked_by', 'source', 'auto_closed']);
        });

        $this->setStatusValues(['present', 'absent', 'late']);
    }

    /**
     * MySQL/MariaDB keep the original raw MODIFY (unchanged behaviour on the server).
     * Other drivers (SQLite in the test suite) don't support MODIFY, so they use the
     * schema builder, which rebuilds the column with the new CHECK constraint.
     */
    private function setStatusValues(array $values): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $list = implode(',', array_map(fn ($v) => "'{$v}'", $values));
            DB::statement("ALTER TABLE staff_attendance MODIFY COLUMN status ENUM({$list}) NOT NULL DEFAULT 'present'");

            return;
        }

        Schema::table('staff_attendance', function (Blueprint $table) use ($values) {
            $table->enum('status', $values)->default('present')->change();
        });
    }
};