<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertOneSpaPerOwner();

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'kind')) {
                $table->string('kind', 20)
                    ->default('new')
                    ->after('business_tier');
            }

            if (! Schema::hasColumn('subscriptions', 'credit_amount')) {
                $table->decimal('credit_amount', 10, 2)
                    ->default(0)
                    ->after('amount');
            }

            if (! Schema::hasColumn('subscriptions', 'superseded_at')) {
                $table->timestamp('superseded_at')
                    ->nullable()
                    ->after('cancelled_at');
            }

            $table->index(
                ['spa_id', 'payment_status', 'expires_at'],
                'subscriptions_spa_paid_expiry_index'
            );
        });

        Schema::table('spas', function (Blueprint $table) {
            // The existing spas_owner_id_foreign index stays for the
            // foreign key; this adds the uniqueness rule next to it.
            $table->unique('owner_id', 'spas_owner_id_unique');
        });

        DB::table('subscriptions')
            ->whereIn('business_tier', ['business', 'professional'])
            ->update(['business_tier' => 'premium']);

        DB::table('spas')
            ->whereIn('business_tier', ['business', 'professional'])
            ->update(['business_tier' => 'premium']);

        DB::table('spas')
            ->where('trial_used', true)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->update(['trial_plan' => 'premium']);
    }

    public function down(): void
    {
        Schema::table('spas', function (Blueprint $table) {
            $table->dropUnique('spas_owner_id_unique');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subscriptions_spa_paid_expiry_index');

            $columns = array_values(array_filter(
                ['kind', 'credit_amount', 'superseded_at'],
                fn (string $column) => Schema::hasColumn('subscriptions', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    /**
     * Fails with a readable message instead of a raw duplicate-key error
     * when an owner already has two spas (soft-deleted ones included).
     */
    private function assertOneSpaPerOwner(): void
    {
        $duplicates = DB::table('spas')
            ->select('owner_id', DB::raw('COUNT(*) AS spa_count'), DB::raw('GROUP_CONCAT(id) AS spa_ids'))
            ->groupBy('owner_id')
            ->having('spa_count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $list = $duplicates
            ->map(fn ($row) => "owner {$row->owner_id}: spas {$row->spa_ids}")
            ->implode('; ');

        throw new RuntimeException(
            'Cannot make spas.owner_id unique, some owners have more than one spa '
            . "(soft-deleted included): {$list}. Remove or reassign the extra spas, then migrate again."
        );
    }
};