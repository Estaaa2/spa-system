<?php
// File path: database/migrations/2026_04_04_000001_create_staff_branch_deployments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_branch_deployments', function (Blueprint $table) {
            $table->id();

            // Who is being deployed
            $table->foreignId('staff_id')
                ->constrained('staff')
                ->onDelete('cascade');

            // Which spa this belongs to (for multi-tenancy scoping)
            $table->foreignId('spa_id')
                ->constrained('spas')
                ->onDelete('cascade');

            // Who submitted the request (HR)
            $table->foreignId('requested_by')
                ->constrained('users')
                ->onDelete('cascade');

            // Who reviewed it (Owner) — nullable until reviewed
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Branch movement
            $table->foreignId('from_branch_id')
                ->constrained('branches')
                ->onDelete('cascade')
                ->comment('Branch the staff is moving from');

            $table->foreignId('to_branch_id')
                ->constrained('branches')
                ->onDelete('cascade')
                ->comment('Branch the staff is being deployed to');

            // Schedule
            $table->date('start_date')
                ->comment('When the deployment activates');

            $table->date('end_date')
                ->nullable()
                ->comment('When the deployment ends; null = use is_permanent flag');

            $table->boolean('is_permanent')
                ->default(false)
                ->comment('If true, end_date is ignored and staff stays permanently');

            // Workflow status
            $table->enum('status', [
                'pending',    // Submitted by HR, awaiting Owner review
                'approved',   // Owner approved, waiting for start_date
                'rejected',   // Owner rejected with reason
                'active',     // start_date passed, staff is now at to_branch
                'completed',  // end_date passed (non-permanent), staff reverted
                'cancelled',  // HR cancelled before Owner reviewed
            ])->default('pending');

            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable()->comment('HR notes when submitting');

            // Audit timestamps for when deployment was actually applied/reverted
            $table->timestamp('deployed_at')->nullable();
            $table->timestamp('reverted_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Prevent overlapping active requests for the same staff
            $table->index(['staff_id', 'status']);
            $table->index(['spa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_branch_deployments');
    }
};
