<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * LOCAL TESTING ONLY — removes the 8 accounts made by IdentityVerificationTestSeeder
 * (idv.*@levictas.test) and nothing else.
 *
 *   php artisan db:seed --class=IdentityVerificationTestRemoveSeeder   (remove only)
 *   php artisan db:seed --class=IdentityVerificationTestResetSeeder    (remove, then seed again)
 *
 * Removed with each account: its staff record (and the attendance, leave,
 * availability and pay rows that belong to it), its verification requests and
 * file-view log, its role, login sessions and app tokens, and its photo, ID and
 * selfie files. The test spa, its branches, the other test accounts and all
 * other data stay.
 *
 * Bookings assigned to one of these accounts are kept and become "Not assigned".
 *
 * Stops without removing anything if one of these accounts appears in a record
 * that must not be changed silently (a payslip, a rating, a reassignment
 * request, an expense, payroll setup). It names the table so you can decide:
 * delete that record yourself, or reset all test data with
 * PayrollTestAccountsResetSeeder.
 *
 * Runs in one transaction. Refuses to run in production.
 */
class IdentityVerificationTestRemoveSeeder extends Seeder
{
    /** table => columns that point at the user (no cascade in the schema) */
    private const USER_BLOCKERS = [
        'ratings'               => ['therapist_id', 'customer_id'],
        'reassignment_requests' => ['requested_by', 'old_therapist_id', 'new_therapist_id', 'reviewed_by'],
        'expenses'              => ['requested_by', 'reviewed_by'],
        'staff_pay_profiles'    => ['created_by'],
        'staff_recurring_items' => ['created_by'],
        'commission_rules'      => ['created_by'],
        'payroll_runs'          => ['generated_by'],
        'spas'                  => ['owner_id'],
    ];

    /** table => column that points at the staff row (no cascade) */
    private const STAFF_BLOCKERS = [
        'payslips' => 'staff_id',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('IdentityVerificationTestRemoveSeeder refuses to run in production.');

            return;
        }

        $userIds = DB::table('users')->whereIn('email', IdentityVerificationTestSeeder::emails())->pluck('id')->all();

        if ($userIds === []) {
            $this->command?->info('No identity test accounts found — nothing to remove.');

            return;
        }

        $staffIds = DB::table('staff')->whereIn('user_id', $userIds)->pluck('id')->all();

        $blockers = $this->blockers($userIds, $staffIds);

        if ($blockers !== []) {
            $this->command?->error('Nothing was removed. These records still point at the identity test accounts:');
            $this->command?->table(['Table', 'Rows'], $blockers);
            $this->command?->line('Delete those records (e.g. the payroll run that includes these staff), or reset all test data:');
            $this->command?->line('  php artisan db:seed --class=PayrollTestAccountsResetSeeder');
            $this->command?->line('  php artisan db:seed --class=IdentityVerificationTestSeeder');

            return;
        }

        $unassigned = DB::table('bookings')->whereIn('therapist_id', $userIds)->count();

        DB::transaction(function () use ($userIds, $staffIds) {
            DB::table('model_has_roles')->where('model_type', 'App\\Models\\User')->whereIn('model_id', $userIds)->delete();
            DB::table('model_has_permissions')->where('model_type', 'App\\Models\\User')->whereIn('model_id', $userIds)->delete();

            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')->where('tokenable_type', 'App\\Models\\User')->whereIn('tokenable_id', $userIds)->delete();
            }

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            }

            // Staff first (staff.user_id has no cascade); its children cascade.
            DB::table('staff')->whereIn('id', $staffIds)->delete();

            // Hard delete (User uses SoftDeletes). Verification rows, file views,
            // leave requests and availability cascade; bookings.therapist_id → NULL.
            DB::table('users')->whereIn('id', $userIds)->delete();
        });

        $this->deleteFiles($userIds);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            'Removed %d identity test account(s) and their files.%s Other data was not touched.',
            count($userIds),
            $unassigned > 0 ? " {$unassigned} booking(s) assigned to them are now \"Not assigned\"." : ''
        ));
    }

    /** @return list<array{0: string, 1: int}> */
    private function blockers(array $userIds, array $staffIds): array
    {
        $found = [];

        foreach (self::USER_BLOCKERS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));

            if ($columns === []) {
                continue;
            }

            $count = DB::table($table)->where(function ($q) use ($columns, $userIds) {
                foreach ($columns as $column) {
                    $q->orWhereIn($column, $userIds);
                }
            })->count();

            if ($count > 0) {
                $found[] = [$table, $count];
            }
        }

        foreach (self::STAFF_BLOCKERS as $table => $column) {
            if ($staffIds !== [] && Schema::hasTable($table)) {
                $count = DB::table($table)->whereIn($column, $staffIds)->count();

                if ($count > 0) {
                    $found[] = [$table, $count];
                }
            }
        }

        return $found;
    }

    private function deleteFiles(array $userIds): void
    {
        $disk = Storage::disk(config('identity.disk', 'local'));

        foreach ($userIds as $id) {
            $disk->deleteDirectory(config('identity.directories.profile_photos', 'profile-photos').'/'.$id);
            $disk->deleteDirectory(config('identity.directories.verifications', 'identity-verifications').'/'.$id);
        }
    }
}
