<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DSS revamp (Q5 B, D6): recommendations found by the daily `dss:generate` job,
 * plus the owner's decision on each (accept / dismiss). One row per fingerprint
 * (rule + branch + target + period), so a dismissed item stays hidden until the
 * situation changes into a new period. Accepted and dismissed rows are the
 * decision history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dss_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope', 10)->default('branch');          // branch | spa
            $table->string('rule_key', 30);                          // r1_staffing, r2_hire, ...
            $table->string('fingerprint', 191);
            $table->string('priority', 10)->default('medium');       // high | medium | low
            $table->string('title');
            $table->text('message');
            $table->json('details')->nullable();                     // "why" lines + the numbers behind them
            $table->json('action')->nullable();                      // {label, url}
            $table->string('status', 20)->default('open');           // open | accepted | dismissed | expired
            $table->string('dismiss_reason', 30)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['spa_id', 'fingerprint']);
            $table->index(['spa_id', 'branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dss_recommendations');
    }
};
