<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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
