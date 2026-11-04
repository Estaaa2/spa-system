<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_verification_documents', function (Blueprint $table) {
            $table->date('expiry_date')
                ->nullable()
                ->after('file_size');

            $table->string('expiry_date_raw')
                ->nullable()
                ->after('expiry_date');

            $table->string('expiry_detection_status')
                ->default('not_scanned')
                ->after('expiry_date_raw');

            $table->string('expiry_detection_source')
                ->nullable()
                ->after('expiry_detection_status');

            $table->timestamp('expiry_scanned_at')
                ->nullable()
                ->after('expiry_detection_source');

            $table->timestamp('expiry_verified_at')
                ->nullable()
                ->after('expiry_scanned_at');

            $table->foreignId('expiry_verified_by')
                ->nullable()
                ->after('expiry_verified_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('spa_verification_documents', function (Blueprint $table) {
            $table->dropForeign([
                'expiry_verified_by',
            ]);

            $table->dropColumn([
                'expiry_date',
                'expiry_date_raw',
                'expiry_detection_status',
                'expiry_detection_source',
                'expiry_scanned_at',
                'expiry_verified_at',
                'expiry_verified_by',
            ]);
        });
    }
};
