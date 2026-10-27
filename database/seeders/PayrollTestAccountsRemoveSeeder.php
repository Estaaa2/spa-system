<?php

namespace Database\Seeders;

use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * LOCAL TESTING ONLY — removes the fake spa made by PayrollTestAccountsSeeder and
 * everything inside it, and nothing else.
 *
 *   php artisan db:seed --class=PayrollTestAccountsRemoveSeeder   (remove only)
 *   php artisan db:seed --class=PayrollTestAccountsResetSeeder    (remove, then seed again)
 *
 * What counts as test data: the spa named PayrollTestAccountsSeeder::SPA_NAME and every
 * row that belongs to it (branches, users, staff, attendance, bookings, payroll runs,
 * rules …) — including rows you created inside that spa while testing. Other spas,
 * admin accounts, roles and permissions are not touched.
 *
 * Runs in one transaction: if any row blocks the delete, nothing is removed and the
 * blocking table is named. Refuses to run in production.
 */
class PayrollTestAccountsRemoveSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('PayrollTestAccountsRemoveSeeder refuses to run in production.');

            return;
        }

        $spaId = DB::table('spas')->where('name', PayrollTestAccountsSeeder::SPA_NAME)->value('id');
        if ($spaId === null) {
            $this->command?->info('No test spa found — nothing to remove.');

            return;
        }

        try {
            $counts = DB::transaction(fn () => $this->removeSpa((int) $spaId));
        } catch (QueryException $e) {
            $this->command?->error('Nothing was removed: a row outside the test spa still points at its data.');
            $this->command?->line($e->getMessage());

            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command?->info(sprintf(
            'Removed the test spa: %d user(s), %d staff, %d payroll run(s). Other data was not touched.',
            $counts['users'], $counts['staff'], $counts['runs'],
        ));
    }

    /** @return array{users: int, staff: int, runs: int} */
    private function removeSpa(int $spaId): array
    {
        $userIds  = DB::table('users')->where('spa_id', $spaId)
            ->orWhereIn('id', DB::table('spas')->where('id', $spaId)->select('owner_id'))
            ->pluck('id')->all();
        $staffIds = DB::table('staff')->where('spa_id', $spaId)->pluck('id')->all();
        $bookingIds = DB::table('bookings')->where('spa_id', $spaId)->pluck('id')->all();
        $emails   = DB::table('users')->whereIn('id', $userIds)->pluck('email')->all();
        $runs     = DB::table('payroll_runs')->where('spa_id', $spaId)->count();

        // 1. Rows whose foreign keys would BLOCK deleting the spa, its users or its
        //    staff (no cascade in the schema). Children of each go with them by cascade.
        $this->deleteWhereIn('reassignment_requests', 'booking_id', $bookingIds);
        $this->deleteWhereIn('reassignment_requests', 'requested_by', $userIds);
        $this->deleteBySpa('booking_consumptions', $spaId);
        $this->deleteBySpa('vendor_bills', $spaId);
        $this->deleteBySpa('goods_receipts', $spaId);
        $this->deleteBySpa('purchase_orders', $spaId);
        $this->deleteBySpa('purchase_requests', $spaId);
        $this->deleteBySpa('expenses', $spaId);
        $this->deleteBySpa('stock_transfers', $spaId);
        $this->deleteWhereIn('payslips', 'staff_id', $staffIds);   // payslip lines cascade
        $this->deleteBySpa('payroll_runs', $spaId);
        $this->deleteBySpa('commission_rules', $spaId);
        $this->deleteWhereIn('staff_recurring_items', 'staff_id', $staffIds);
        $this->deleteWhereIn('staff_pay_profiles', 'staff_id', $staffIds);

        // 2. The spa itself (hard delete, past the soft-delete column). The schema
        //    cascades branches, users, staff, attendance, bookings, leave, etc.
        DB::table('spas')->where('id', $spaId)->delete();
        // Any test user the cascade did not reach (e.g. spa_id cleared during testing).
        DB::table('users')->whereIn('id', $userIds)->delete();

        // 3. Rows keyed by user id or email with no foreign key to cascade.
        if ($userIds !== []) {
            foreach (['model_has_roles', 'model_has_permissions'] as $pivot) {
                DB::table($pivot)->where('model_type', \App\Models\User::class)->whereIn('model_id', $userIds)->delete();
            }
            $this->deleteWhereIn('sessions', 'user_id', $userIds);
            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')->where('tokenable_type', \App\Models\User::class)->whereIn('tokenable_id', $userIds)->delete();
            }
        }
        $this->deleteWhereIn('password_reset_tokens', 'email', $emails);

        return ['users' => count($userIds), 'staff' => count($staffIds), 'runs' => $runs];
    }

    private function deleteBySpa(string $table, int $spaId): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, 'spa_id')) {
            DB::table($table)->where('spa_id', $spaId)->delete();
        }
    }

    /** @param list<int|string> $values */
    private function deleteWhereIn(string $table, string $column, array $values): void
    {
        if ($values !== [] && Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            DB::table($table)->whereIn($column, $values)->delete();
        }
    }
}
