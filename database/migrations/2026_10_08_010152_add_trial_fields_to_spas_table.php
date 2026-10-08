<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spas', function (Blueprint $table) {
            if (!Schema::hasColumn('spas', 'trial_plan')) {
                $table->string('trial_plan')
                    ->nullable()
                    ->after('business_tier');
            }

            if (!Schema::hasColumn('spas', 'trial_started_at')) {
                $table->timestamp('trial_started_at')
                    ->nullable()
                    ->after('trial_plan');
            }

            if (!Schema::hasColumn('spas', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')
                    ->nullable()
                    ->after('trial_started_at');
            }

            if (!Schema::hasColumn('spas', 'trial_used')) {
                $table->boolean('trial_used')
                    ->default(false)
                    ->after('trial_ends_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spas', function (Blueprint $table) {
            $columns = [];

            foreach ([
                'trial_plan',
                'trial_started_at',
                'trial_ends_at',
                'trial_used',
            ] as $column) {
                if (Schema::hasColumn('spas', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};