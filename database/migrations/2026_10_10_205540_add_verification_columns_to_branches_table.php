<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Same four values the spa uses:
            // unverified, pending, verified, rejected
            $table->string('verification_status', 20)
                ->default('unverified')
                ->after('is_main')
                ->index();

            $table->text('verification_remarks')
                ->nullable()
                ->after('verification_status');

            $table->timestamp('verified_at')
                ->nullable()
                ->after('verification_remarks');

            $table->foreignId('verified_by')
                ->nullable()
                ->after('verified_at')
                ->constrained('users')
                ->nullOnDelete();

            // Deadline for branches that existed before this feature.
            $table->timestamp('verification_due_at')
                ->nullable()
                ->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropIndex(['verification_status']);

            $table->dropColumn([
                'verification_status',
                'verification_remarks',
                'verified_at',
                'verification_due_at',
            ]);
        });
    }
};
