<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropForeign(['job_posting_id']);
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->unsignedBigInteger('job_posting_id')
                ->nullable()
                ->change();

            $table->foreign('job_posting_id')
                ->references('id')
                ->on('job_postings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropForeign(['job_posting_id']);
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->foreign('job_posting_id')
                ->references('id')
                ->on('job_postings')
                ->cascadeOnDelete();
        });
    }
};
