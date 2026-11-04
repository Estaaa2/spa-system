<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spa_verification_document_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('spa_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('document_type');

            $table->string('file_path');

            $table->string('file_name')
                ->nullable();

            $table->string('mime_type')
                ->nullable();

            $table->unsignedBigInteger('file_size')
                ->nullable();

            $table->date('expiry_date')
                ->nullable();

            $table->string('expiry_date_raw')
                ->nullable();

            $table->string('expiry_detection_status')
                ->nullable();

            $table->string('expiry_detection_source')
                ->nullable();

            $table->timestamp('expiry_scanned_at')
                ->nullable();

            $table->timestamp('expiry_verified_at')
                ->nullable();

            $table->foreignId('expiry_verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('replaced_at');

            $table->foreignId('replaced_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'spa_id',
                'document_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'spa_verification_document_histories'
        );
    }
};
