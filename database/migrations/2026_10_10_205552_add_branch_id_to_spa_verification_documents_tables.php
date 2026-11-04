<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add branch_id and the NEW unique index first.
        //    NULL branch_id = the document belongs to the spa.
        Schema::table('spa_verification_documents', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('spa_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->unique(
                ['spa_id', 'branch_id', 'document_type'],
                'svd_spa_branch_type_unique'
            );
        });

        // 2. Only now drop the OLD unique index. The spa_id foreign key
        //    needs an index that starts with spa_id, and the new one
        //    above covers it.
        Schema::table('spa_verification_documents', function (Blueprint $table) {
            $table->dropUnique(
                'spa_verification_documents_spa_id_document_type_unique'
            );
        });

        // 3. Same column on the history table.
        Schema::table('spa_verification_document_histories', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('spa_id')
                ->constrained('branches')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('spa_verification_document_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        // This fails if a spa already has two documents of the same type
        // (for example two Business Permits). Remove the extra rows first.
        Schema::table('spa_verification_documents', function (Blueprint $table) {
            $table->unique(['spa_id', 'document_type']);
        });

        Schema::table('spa_verification_documents', function (Blueprint $table) {
            $table->dropUnique('svd_spa_branch_type_unique');
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
