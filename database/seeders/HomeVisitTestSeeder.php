<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchProfile;
use App\Models\OperatingHours;
use App\Models\Spa;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * LOCAL TESTING ONLY — a test customer with online home-service bookings, for
 * the staff-verification Phase 4/5 checks (arrival code, therapist card,
 * unassigned booking), without paying through PayMongo.
 *
 *   php artisan db:seed --class=PayrollTestAccountsSeeder        (once)
 *   php artisan db:seed --class=IdentityVerificationTestSeeder   (once)
 *   php artisan db:seed --class=HomeVisitTestSeeder              (once)
 *   php artisan db:seed --class=HomeVisitTestResetSeeder         (fresh copy of only this data)
 *   php artisan db:seed --class=HomeVisitTestRemoveSeeder        (remove only this data)
 *
 * Creates, in Test Branch A:
 *   - customer homevisit.customer@levictas.test (same password as the other test accounts)
 *   - A: today, about an hour from now, home service, therapist Vera  → arrival code
 *   - B: tomorrow 10:00, home service, therapist Vera                 → upcoming visit
 *   - C: tomorrow 14:00, home service, no therapist                   → "No Therapist Assigned"
 * Also adds opening hours (09:00–21:00) and a listed branch profile to Test
 * Branch A if it has none, so the customer booking form can be tried too.
 * If the test customer already exists it stops and points to the Reset seeder.
 * No emails are sent.
 */
class HomeVisitTestSeeder extends Seeder
{
    public const CUSTOMER_EMAIL = 'homevisit.customer@levictas.test';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('HomeVisitTestSeeder refuses to run in production.');

            return;
        }

        $spa = Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->first();
        $branch = $spa ? Branch::withoutGlobalScopes()->where('spa_id', $spa->id)->orderByDesc('is_main')->orderBy('id')->first() : null;
        $vera = User::where('email', 'idv.vera@levictas.test')->first();
        $treatment = $branch ? Treatment::withoutGlobalScopes()->where('branch_id', $branch->id)->where('service_type', '!=', 'in_branch_only')->first() : null;

        if (! $spa || ! $branch || ! $vera || ! $treatment) {
            $this->command?->error('Run PayrollTestAccountsSeeder and IdentityVerificationTestSeeder first.');

            return;
        }

        if (User::withTrashed()->where('email', self::CUSTOMER_EMAIL)->exists()) {
            $this->command?->warn('The home-visit test data already exists. For a fresh copy of only this data, run: php artisan db:seed --class=HomeVisitTestResetSeeder');

            return;
        }

        $codes = DB::transaction(function () use ($spa, $branch, $vera, $treatment) {
            $customer = new User();
            $customer->forceFill([
                'email' => self::CUSTOMER_EMAIL, 'first_name' => 'Cara', 'last_name' => 'Customer (test)',
                'password' => Hash::make(PayrollTestAccountsSeeder::PASSWORD), 'status' => 'active',
                'email_verified_at' => now(), 'phone' => '09170000001', 'address' => 'Sample street, Imus, Cavite',
            ])->saveQuietly();
            $customer->syncRoles(['customer']);

            $this->ensureBranchCanBeBooked($branch);

            // A: today, at the next full hour, so it is still "reserved" when you
            // test (the status moves on its own once the start time passes). Late
            // at night it starts in 5 minutes instead, and ends by 23:59.
            $start = now()->addHour()->startOfHour();
            if (! $start->isToday()) {
                $start = now()->addMinutes(5)->startOfMinute();
            }

            $rows = [
                'A' => [today(), $start->format('H:i:s'), $vera->id],
                'B' => [today()->addDay(), '10:00:00', $vera->id],
                'C' => [today()->addDay(), '14:00:00', null],
            ];

            $made = [];
            foreach ($rows as $key => [$date, $time, $therapistId]) {
                $made[$key] = Booking::create([
                    'spa_id' => $spa->id, 'branch_id' => $branch->id, 'customer_user_id' => $customer->id,
                    'booking_source' => 'online', 'status' => 'reserved', 'service_type' => 'in_home',
                    'treatment' => 'treatment_'.$treatment->id, 'therapist_id' => $therapistId,
                    'payment_status' => 'partially_paid', 'total_amount' => $treatment->price,
                    'amount_paid' => round($treatment->price * 0.2, 2), 'balance_amount' => round($treatment->price * 0.8, 2),
                    'customer_name' => 'Cara Customer (test)', 'customer_email' => self::CUSTOMER_EMAIL,
                    'customer_phone' => '09170000001', 'customer_address' => 'Sample street, Imus, Cavite',
                    'appointment_date' => $date->toDateString(), 'start_time' => $time,
                    'end_time' => $this->endTime($time, (int) ($treatment->duration ?: 60)),
                ]);
            }

            return $made;
        });

        $this->command?->info('Home-visit test data created. Customer: '.self::CUSTOMER_EMAIL.' / '.PayrollTestAccountsSeeder::PASSWORD);
        $this->command?->table(['Booking', 'When', 'Therapist'], [
            ['A', 'Today '.Carbon::parse($codes['A']->start_time)->format('g:i A'), 'Vera (verified)'],
            ['B', 'Tomorrow 10:00 AM', 'Vera (verified)'],
            ['C', 'Tomorrow 2:00 PM', 'None (assign it on Appointments)'],
        ]);
        $this->command?->line('The arrival codes are only shown to the customer: log in as the customer and open My Appointments.');
    }

    /** Start + duration, but never past midnight (an end before the start would read as finished). */
    private function endTime(string $start, int $minutes): string
    {
        $end = Carbon::parse($start)->addMinutes($minutes)->format('H:i:s');

        return $end > $start ? $end : '23:59:00';
    }

    private function ensureBranchCanBeBooked(Branch $branch): void
    {
        if (! OperatingHours::where('branch_id', $branch->id)->exists()) {
            foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
                OperatingHours::create([
                    'branch_id' => $branch->id, 'day_of_week' => $day,
                    'opening_time' => '09:00:00', 'closing_time' => '21:00:00', 'is_closed' => false,
                ]);
            }
        }

        if (! BranchProfile::where('branch_id', $branch->id)->exists()) {
            $profile = new BranchProfile();
            $profile->forceFill([
                'branch_id' => $branch->id, 'is_listed' => true, 'description' => 'Fake test branch.',
                'address' => 'Sample address A, Imus, Cavite', 'city' => 'Imus', 'phone' => '09170000000',
                'latitude' => 14.4297, 'longitude' => 120.9367,
            ])->save();
        }
    }
}
