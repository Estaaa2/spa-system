<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Spa;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Navigation\NavBadges;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * LOCAL TESTING ONLY — bookings that ended yesterday but were never closed, for
 * the booking lifecycle fix Phase 2 checks (Needs Checkout list, badge "!",
 * Dashboard alert, assign therapist, late arrival confirmation).
 *
 *   php artisan db:seed --class=PayrollTestAccountsSeeder        (once)
 *   php artisan db:seed --class=IdentityVerificationTestSeeder   (once)
 *   php artisan db:seed --class=OverdueBookingsTestSeeder        (once)
 *   php artisan db:seed --class=OverdueBookingsTestResetSeeder   (fresh copy of only this data)
 *   php artisan db:seed --class=OverdueBookingsTestRemoveSeeder  (remove only this data)
 *
 * Creates, in Test Branch A, for customer overdue.customer@levictas.test, all
 * dated yesterday:
 *   O1  10:00  walk-in, in branch, therapist Vera, unpaid, pending     → plain checkout
 *   O2  12:00  walk-in, in branch, therapist Vera, half paid, ongoing  → checkout the rest
 *   O3  14:00  online home service, Vera, 20% deposit, arrival NOT confirmed
 *                                                                      → "Confirm arrival" first
 *   O4  16:00  online, in branch, NO therapist, 20% deposit            → "Assign therapist" first
 * If the test customer already exists it stops and points to the Reset seeder.
 * No emails are sent (rows are saved quietly). One transaction. Refuses production.
 */
class OverdueBookingsTestSeeder extends Seeder
{
    public const CUSTOMER_EMAIL = 'overdue.customer@levictas.test';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('OverdueBookingsTestSeeder refuses to run in production.');

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
            $this->command?->warn('The overdue-booking test data already exists. For a fresh copy of only this data, run: php artisan db:seed --class=OverdueBookingsTestResetSeeder');

            return;
        }

        $price = (float) $treatment->price;
        $minutes = (int) ($treatment->duration ?: 60);
        $yesterday = today()->subDay()->toDateString();

        DB::transaction(function () use ($spa, $branch, $vera, $treatment, $price, $minutes, $yesterday) {
            $customer = new User();
            $customer->forceFill([
                'email' => self::CUSTOMER_EMAIL, 'first_name' => 'Olive', 'last_name' => 'Overdue (test)',
                'password' => Hash::make(PayrollTestAccountsSeeder::PASSWORD), 'status' => 'active',
                'email_verified_at' => now(), 'phone' => '09170000002', 'address' => 'Sample street, Imus, Cavite',
            ])->saveQuietly();
            $customer->syncRoles(['customer']);

            //        key    start      source     service      therapist   paid share  status
            $rows = [
                'O1' => ['10:00:00', 'walk_in', 'in_branch', $vera->id, 0.0, 'pending'],
                'O2' => ['12:00:00', 'walk_in', 'in_branch', $vera->id, 0.5, 'ongoing'],
                'O3' => ['14:00:00', 'online',  'in_home',   $vera->id, 0.2, 'pending'],
                'O4' => ['16:00:00', 'online',  'in_branch', null,      0.2, 'pending'],
            ];

            foreach ($rows as $key => [$start, $source, $service, $therapistId, $share, $status]) {
                $paid = round($price * $share, 2);

                $booking = new Booking();
                $booking->forceFill([
                    'spa_id' => $spa->id, 'branch_id' => $branch->id, 'customer_user_id' => $customer->id,
                    'booking_source' => $source, 'status' => $status, 'service_type' => $service,
                    'treatment' => 'treatment_'.$treatment->id, 'therapist_id' => $therapistId,
                    'payment_status' => $paid <= 0 ? 'unpaid' : 'partially_paid',
                    'total_amount' => $price, 'amount_paid' => $paid, 'balance_amount' => round($price - $paid, 2),
                    'customer_name' => "Olive Overdue ({$key})", 'customer_email' => self::CUSTOMER_EMAIL,
                    'customer_phone' => '09170000002',
                    'customer_address' => $service === 'in_home' ? 'Sample street, Imus, Cavite' : null,
                    'appointment_date' => $yesterday, 'start_time' => $start,
                    'end_time' => \Illuminate\Support\Carbon::parse($start)->addMinutes($minutes)->format('H:i:s'),
                    'arrival_code' => $source === 'online' && $service === 'in_home' ? Booking::newArrivalCode() : null,
                ])->saveQuietly();
            }
        });

        NavBadges::bump((int) $spa->id);

        $this->command?->info('Overdue-booking test data created (all dated yesterday, Test Branch A).');
        $this->command?->table(['Booking', 'Start', 'What to try'], [
            ['O1', '10:00 AM', 'Process → Check out (collect the full price)'],
            ['O2', '12:00 PM', 'Already started: Process → Check out the other half'],
            ['O3', '2:00 PM', 'Home visit: Confirm arrival (reason) first, then check out'],
            ['O4', '4:00 PM', 'No therapist: Assign therapist first, then check out'],
        ]);
    }
}
