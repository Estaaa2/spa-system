<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoCatalog;
use Database\Seeders\Demo\DemoGuard;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * DEMO DATA — removes what DemoDataSeeder made, and nothing else.
 *
 *   php artisan db:seed --class=DemoDataRemoveSeeder --force   (remove only)
 *   php artisan db:seed --class=DemoDataResetSeeder --force    (remove, then seed again)
 *
 * What counts as demo data:
 *   - the spas named in DemoCatalog::SPAS (names ending in "(Demo)") and every row
 *     inside them — branches, staff, bookings, ratings, attendance, payroll,
 *     inventory, procurement, hiring … including rows you added while demoing,
 *     and bookings real customers may have made at a demo spa;
 *   - every account whose email ends in @levictas-demo.test (the demo customers);
 *   - the images under storage/app/private|public/demo-data.
 * Real spas, real users, admin accounts, roles and permissions are not touched.
 *
 * Runs in one transaction: if any row outside the demo data still points at it,
 * nothing is removed and the blocking table is named.
 * In production it needs DEMO_SEED_ALLOW_PRODUCTION=true, like the seeder.
 */
class DemoDataRemoveSeeder extends Seeder
{
    public function run(): void
    {
        if (! DemoGuard::environmentAllowed($this->command, 'DemoDataRemoveSeeder')) {
            return;
        }

        $spaIds = DB::table('spas')->whereIn('name', DemoCatalog::spaNames())->pluck('id')->map(fn ($id) => (int) $id)->all();
        $customerIds = DB::table('users')->where('email', 'like', DemoCatalog::emailPattern())->pluck('id')->all();

        if ($spaIds === [] && $customerIds === []) {
            $this->command?->info('No demo data found — nothing to remove.');
            $this->deleteFiles();

            return;
        }

        try {
            $users = DB::transaction(function () use ($spaIds) {
                $users = 0;
                foreach ($spaIds as $spaId) {
                    $this->removeInventory($spaId);
                    DB::table('subscriptions')->where('spa_id', $spaId)->delete();
                    $users += $this->removeSpa($spaId);
                }

                // Demo customers and any other demo account left (not tied to a spa).
                $left = DB::table('users')->where('email', 'like', DemoCatalog::emailPattern())->pluck('id')->all();
                if ($left !== []) {
                    DB::table('reschedule_requests')->whereIn('requested_by', $left)->delete();
                    foreach (['model_has_roles', 'model_has_permissions'] as $pivot) {
                        DB::table($pivot)->where('model_type', \App\Models\User::class)->whereIn('model_id', $left)->delete();
                    }
                    if (Schema::hasTable('personal_access_tokens')) {
                        DB::table('personal_access_tokens')->where('tokenable_type', \App\Models\User::class)->whereIn('tokenable_id', $left)->delete();
                    }
                    if (Schema::hasTable('sessions')) {
                        DB::table('sessions')->whereIn('user_id', $left)->delete();
                    }
                    DB::table('users')->whereIn('id', $left)->delete();
                    $users += count($left);
                }
                DB::table('password_reset_tokens')->where('email', 'like', DemoCatalog::emailPattern())->delete();

                return $users;
            });
        } catch (QueryException $e) {
            $this->command?->error('Nothing was removed: a row outside the demo data still points at it.');
            $this->command?->line('  '.$e->getMessage());

            return;
        }

        $this->deleteFiles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($spaIds as $spaId) {
            \App\Services\Navigation\NavBadges::bump($spaId);
        }
        if (class_exists(\App\Services\Hiring\HiringStatus::class)) {
            app(\App\Services\Hiring\HiringStatus::class)->flush();
        }

        $this->command?->info(sprintf('Removed %d demo spa(s) and %d demo account(s), with their files. Other data was not touched.', count($spaIds), $users));
    }

    /**
     * The spa and everything inside it. Rows whose foreign keys would BLOCK the
     * delete (no cascade in the schema) go first; the spa delete then cascades
     * branches, users, staff, attendance, bookings, leave and the rest.
     *
     * @return int number of users removed
     */
    private function removeSpa(int $spaId): int
    {
        $userIds = DB::table('users')->where('spa_id', $spaId)
            ->orWhereIn('id', DB::table('spas')->where('id', $spaId)->select('owner_id'))
            ->pluck('id')->all();
        $staffIds = DB::table('staff')->where('spa_id', $spaId)->pluck('id')->all();
        $bookingIds = DB::table('bookings')->where('spa_id', $spaId)->pluck('id')->all();
        $emails = DB::table('users')->whereIn('id', $userIds)->pluck('email')->all();

        $this->deleteWhereIn('reassignment_requests', 'booking_id', $bookingIds);
        $this->deleteWhereIn('reassignment_requests', 'requested_by', $userIds);
        $this->deleteBySpa('expenses', $spaId);
        $this->deleteWhereIn('payslips', 'staff_id', $staffIds);   // payslip lines cascade
        $this->deleteBySpa('payroll_runs', $spaId);
        $this->deleteBySpa('commission_rules', $spaId);
        $this->deleteWhereIn('staff_recurring_items', 'staff_id', $staffIds);
        $this->deleteWhereIn('staff_pay_profiles', 'staff_id', $staffIds);

        // Hard delete (past the soft-delete column); the schema cascades the rest.
        DB::table('spas')->where('id', $spaId)->delete();
        // Any user the cascade did not reach (e.g. spa_id cleared while demoing).
        DB::table('users')->whereIn('id', $userIds)->delete();

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

        return count($userIds);
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
        foreach (array_chunk($values, 1000) as $chunk) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)->whereIn($column, $chunk)->delete();
            }
        }
    }

    /**
     * Stock rows point at products with RESTRICT, so they go first, children
     * before parents.
     */
    private function removeInventory(int $spaId): void
    {
        $productIds = DB::table('products')->where('spa_id', $spaId)->pluck('id')->all();
        $bookingIds = DB::table('bookings')->where('spa_id', $spaId)->pluck('id')->all();

        foreach (array_chunk($bookingIds, 1000) as $chunk) {
            DB::table('booking_consumptions')->whereIn('booking_id', $chunk)->delete();   // items cascade
        }
        DB::table('booking_consumptions')->where('spa_id', $spaId)->delete();
        DB::table('vendor_bills')->where('spa_id', $spaId)->delete();                      // items cascade
        DB::table('goods_receipts')->where('spa_id', $spaId)->delete();                    // items cascade
        DB::table('purchase_orders')->where('spa_id', $spaId)->delete();
        DB::table('purchase_requests')->where('spa_id', $spaId)->delete();
        DB::table('stock_transfers')->where('spa_id', $spaId)->delete();
        DB::table('stock_movements')->where('spa_id', $spaId)->delete();
        DB::table('product_batches')->where('spa_id', $spaId)->delete();
        DB::table('branch_product_stocks')->where('spa_id', $spaId)->delete();

        if ($productIds !== []) {
            DB::table('treatment_recipe_items')->whereIn('product_id', $productIds)->delete();
            DB::table('supplier_products')->whereIn('product_id', $productIds)->delete();
        }
        DB::table('product_logs')->where('spa_id', $spaId)->delete();
        DB::table('products')->where('spa_id', $spaId)->delete();
        DB::table('suppliers')->where('spa_id', $spaId)->delete();
    }

    private function deleteFiles(): void
    {
        Storage::disk('local')->deleteDirectory(DemoCatalog::FILE_DIR);
        Storage::disk('public')->deleteDirectory(DemoCatalog::FILE_DIR);
        if (config('identity.disk', 'local') !== 'local') {
            Storage::disk(config('identity.disk'))->deleteDirectory(DemoCatalog::FILE_DIR);
        }
    }
}
