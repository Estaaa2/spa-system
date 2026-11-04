<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_verification_documents', function (Blueprint $table) {
            $table->date('owner_expiry_date')
                ->nullable()
                ->after('file_size');

            $table->date('ocr_expiry_date')
                ->nullable()
                ->after('owner_expiry_date');
        });

        Schema::table('spa_verification_document_histories', function (Blueprint $table) {
            $table->date('owner_expiry_date')
                ->nullable()
                ->after('file_size');

            $table->date('ocr_expiry_date')
                ->nullable()
                ->after('owner_expiry_date');
        });

        DB::table('spa_verification_documents')
            ->whereNotNull('expiry_date')
            ->whereNull('expiry_verified_at')
            ->update([
                'ocr_expiry_date' => DB::raw('expiry_date'),
                'expiry_date' => null,
            ]);

        DB::table('spa_verification_document_histories')
            ->whereNotNull('expiry_date')
            ->whereNull('expiry_verified_at')
            ->update([
                'ocr_expiry_date' => DB::raw('expiry_date'),
                'expiry_date' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('spa_verification_documents', function (Blueprint $table) {
            $table->dropColumn([
                'owner_expiry_date',
                'ocr_expiry_date',
            ]);
        });

        Schema::table('spa_verification_document_histories', function (Blueprint $table) {
            $table->dropColumn([
                'owner_expiry_date',
                'ocr_expiry_date',
            ]);
        });
    }
};
