<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When the payment was confirmed. starts_at is not the payment date
        // any more: a renewal paid early or a payment made during the trial
        // starts in the future. Revenue "this month" needs the real date.
        if (! Schema::hasColumn('subscriptions', 'paid_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->timestamp('paid_at')->nullable()->after('payment_status');
            });
        }

        // Backfill: before the rework every paid period started at payment,
        // so the earlier of starts_at and updated_at is the best record.
        DB::table('subscriptions')
            ->where('payment_status', 'paid')
            ->whereNull('paid_at')
            ->orderBy('id')
            ->get(['id', 'starts_at', 'updated_at', 'created_at'])
            ->each(function ($row) {
                $dates = collect([$row->starts_at, $row->updated_at])
                    ->filter()
                    ->map(fn ($date) => Carbon::parse($date));

                $paidAt = $dates->isNotEmpty() ? $dates->min() : Carbon::parse($row->created_at);

                DB::table('subscriptions')->where('id', $row->id)->update(['paid_at' => $paidAt]);
            });

        // Log of every trial extension made by an admin.
        if (! Schema::hasTable('trial_extensions')) {
            Schema::create('trial_extensions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('spa_id')->constrained()->cascadeOnDelete();
                $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedSmallInteger('days');
                $table->timestamp('previous_ends_at')->nullable();
                $table->timestamp('new_ends_at');
                $table->string('reason', 500);
                $table->timestamps();

                $table->index(['spa_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_extensions');

        if (Schema::hasColumn('subscriptions', 'paid_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('paid_at');
            });
        }
    }
};
