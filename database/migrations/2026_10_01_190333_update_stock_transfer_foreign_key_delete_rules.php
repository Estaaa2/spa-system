<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['source_branch_id']);
            $table->dropForeign(['destination_branch_id']);

            $table->foreign('source_branch_id')
                ->references('id')
                ->on('branches')
                ->restrictOnDelete();

            $table->foreign('destination_branch_id')
                ->references('id')
                ->on('branches')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['source_branch_id']);
            $table->dropForeign(['destination_branch_id']);

            $table->foreign('source_branch_id')
                ->references('id')
                ->on('branches')
                ->cascadeOnDelete();

            $table->foreign('destination_branch_id')
                ->references('id')
                ->on('branches')
                ->cascadeOnDelete();
        });
    }
};
