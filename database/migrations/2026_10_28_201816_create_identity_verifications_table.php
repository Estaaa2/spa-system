<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff identity verification, part 2: one row per submission, kept as history.
 *
 * The ID and selfie files are deleted when the admin decides (or the request is
 * withdrawn or superseded); the paths are then NULL and files_purged_at is set.
 * What stays: status, ID type, reviewer, dates, rejection reason, consent record.
 * Rows are deleted with the user account (decision Q21).
 *
 * identity_file_views records every time an admin opened an ID or selfie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('spa_id')->nullable()->constrained('spas')->nullOnDelete();

            // pending | approved | rejected | withdrawn | superseded
            $table->string('status', 20)->default('pending');
            // photo_changed | name_changed (only when status = superseded)
            $table->string('superseded_reason', 30)->nullable();

            $table->string('id_type', 40);
            $table->string('id_image_path')->nullable();
            $table->string('selfie_path')->nullable();

            // What the admin compares against; approval is refused if either
            // has changed since the staff member submitted.
            $table->string('photo_path_reviewed')->nullable();
            $table->string('name_reviewed');

            $table->string('consent_version', 40);
            $table->timestamp('consent_given_at');
            $table->string('consent_channel', 10)->default('web'); // web | api
            $table->timestamp('consent_withdrawn_at')->nullable();

            $table->timestamp('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('files_purged_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('identity_file_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('identity_verification_id')->constrained('identity_verifications')->cascadeOnDelete();
            $table->foreignId('viewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file', 10); // id | selfie | photo
            $table->string('ip', 45)->nullable();
            $table->timestamp('viewed_at');

            $table->index(['identity_verification_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_file_views');
        Schema::dropIfExists('identity_verifications');
    }
};
