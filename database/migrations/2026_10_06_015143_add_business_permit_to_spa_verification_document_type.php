<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ENUM change is MySQL/MariaDB syntax. SQLite (the test suite) has no ENUM
        // column type to modify, so it skips this step — same guard as
        // 2026_08_05_191321_add_time_tracking_to_staff_attendance_table.
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("
            ALTER TABLE spa_verification_documents
            MODIFY document_type ENUM(
                'government_id',
                'dti_sec',
                'bir_certificate',
                'business_permit'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("
            ALTER TABLE spa_verification_documents
            MODIFY document_type ENUM(
                'government_id',
                'dti_sec',
                'bir_certificate'
            ) NOT NULL
        ");
    }
};
