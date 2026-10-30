<?php

namespace Database\Seeders;

use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LOCAL TESTING ONLY — removes what HomeVisitTestSeeder made, and nothing else.
 *
 *   php artisan db:seed --class=HomeVisitTestRemoveSeeder   (remove only)
 *   php artisan db:seed --class=HomeVisitTestResetSeeder    (remove, then seed again)
 *
 * Removes the test customer homevisit.customer@levictas.test and every booking
 * of that customer (also ones you made yourself while logged in as them), with
 * the rows that belong to those bookings: reassignment and reschedule
 * requests, ratings, and the "used products" record made when a booking is
 * completed.
 *
 * Stops without removing anything if a completed booking used products, because
 * stock was taken out for it and deleting the record would not put it back. It
 * names the bookings so you can decide.
 *
 * Kept: the opening hours and branch profile the seeder added to Test Branch A
 * (the booking form needs them; they belong to the fake test spa), the staff
 * accounts, and all other data. Runs in one transaction. Refuses production.
 */
class HomeVisitTestRemoveSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('HomeVisitTestRemoveSeeder refuses to run in production.');

            return;
        }

        $customerId = DB::table('users')->where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->value('id');

        if (! $customerId) {
            $this->command?->info('No home-visit test data found — nothing to remove.');

            return;
        }

        $bookingIds = DB::table('bookings')->where('customer_user_id', $customerId)->pluck('id')->all();

        $withProducts = $this->bookingsThatUsedProducts($bookingIds);
        if ($withProducts !== []) {
            $this->command?->error('Nothing was removed. These test bookings were completed and took products out of stock:');
            $this->command?->line('  Booking IDs: '.implode(', ', $withProducts));
            $this->command?->line('Undo the stock change in Inventory, then delete their rows in booking_consumption_items and booking_consumptions,');
            $this->command?->line('or reset all test data with PayrollTestAccountsResetSeeder.');

            return;
        }

        try {
            DB::transaction(function () use ($customerId, $bookingIds) {
                if ($bookingIds !== []) {
                    // booking_consumptions blocks deleting a booking (restrictOnDelete).
                    // Only empty ones are left at this point (no stock was used).
                    if (Schema::hasTable('booking_consumptions')) {
                        DB::table('booking_consumptions')->whereIn('booking_id', $bookingIds)->delete();
                    }

                    // Reassignment/reschedule requests and ratings cascade; payments and
                    // stock movements keep their row with booking_id = NULL.
                    DB::table('bookings')->whereIn('id', $bookingIds)->delete();
                }

                DB::table('model_has_roles')->where('model_type', 'App\\Models\\User')->where('model_id', $customerId)->delete();
                DB::table('model_has_permissions')->where('model_type', 'App\\Models\\User')->where('model_id', $customerId)->delete();

                if (Schema::hasTable('personal_access_tokens')) {
                    DB::table('personal_access_tokens')->where('tokenable_type', 'App\\Models\\User')->where('tokenable_id', $customerId)->delete();
                }
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $customerId)->delete();
                }

                // Hard delete (User uses SoftDeletes).
                DB::table('users')->where('id', $customerId)->delete();
            });
        } catch (QueryException $e) {
            $this->command?->error('Nothing was removed. Another record still points at the test customer or their bookings:');
            $this->command?->line('  '.$e->getMessage());

            return;
        }

        $this->command?->info(sprintf(
            'Removed the home-visit test customer and %d booking(s). Other data was not touched.',
            count($bookingIds)
        ));
    }

    /** @return list<int> booking ids whose completion took products out of stock */
    private function bookingsThatUsedProducts(array $bookingIds): array
    {
        if ($bookingIds === [] || ! Schema::hasTable('booking_consumptions') || ! Schema::hasTable('booking_consumption_items')) {
            return [];
        }

        return DB::table('booking_consumptions')
            ->join('booking_consumption_items', 'booking_consumption_items.booking_consumption_id', '=', 'booking_consumptions.id')
            ->whereIn('booking_consumptions.booking_id', $bookingIds)
            ->distinct()
            ->pluck('booking_consumptions.booking_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
