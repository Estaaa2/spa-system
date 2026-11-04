<?php

namespace Database\Seeders;

use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * LOCAL TESTING ONLY — removes the three fake spas made by PlanVariantTestSeeder
 * and everything inside them, and nothing else.
 *
 *   php artisan db:seed --class=PlanVariantTestRemoveSeeder   (remove only)
 *   php artisan db:seed --class=PlanVariantTestResetSeeder    (remove, then seed again)
 *
 * What counts as test data: the spas named in PlanVariantTestSeeder::SPAS and
 * every row that belongs to them (branches, users, staff, bookings, the fake
 * Basic subscription …), including rows you created inside them while testing.
 * Uses the same removal steps as PayrollTestAccountsRemoveSeeder. Other spas,
 * admin accounts, roles and permissions are not touched.
 *
 * Runs in one transaction: if any row blocks the delete, nothing is removed and
 * the error is shown. Refuses to run in production.
 */
class PlanVariantTestRemoveSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('PlanVariantTestRemoveSeeder refuses to run in production.');

            return;
        }

        $spaIds = DB::table('spas')->whereIn('name', PlanVariantTestSeeder::spaNames())->pluck('id')->all();

        if ($spaIds === []) {
            $this->command?->info('No plan-variant test spas found — nothing to remove.');

            return;
        }

        $remover = new PayrollTestAccountsRemoveSeeder();

        try {
            $users = DB::transaction(function () use ($spaIds, $remover) {
                $users = 0;
                foreach ($spaIds as $spaId) {
                    // Subscriptions are not in the payroll seeder's list; remove them first.
                    DB::table('subscriptions')->where('spa_id', $spaId)->delete();
                    $users += $remover->removeSpa((int) $spaId)['users'];
                }

                return $users;
            });
        } catch (QueryException $e) {
            $this->command?->error('Nothing was removed: a row outside the test spas still points at their data.');
            $this->command?->line($e->getMessage());

            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command?->info(sprintf(
            'Removed %d plan-variant test spa(s) and %d user(s). Other data was not touched.',
            count($spaIds), $users,
        ));
    }
}
