<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The owner can now say a document has no expiration date (PhilSys,
 * UMID, Senior Citizen or TIN IDs, SEC certificates). This records the
 * owner's statement only; the administrator still confirms it.
 *
 * Safe to run again: each column is added only when it is missing.
 */
return new class extends Migration
{
    private const TABLES = [
        'spa_verification_documents',
        'spa_verification_document_histories',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (Schema::hasColumn($tableName, 'owner_no_expiry')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('owner_no_expiry')
                    ->default(false)
                    ->after('owner_expiry_date');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasColumn($tableName, 'owner_no_expiry')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('owner_no_expiry');
            });
        }
    }
};
