<?php

namespace Database\Seeders;

use App\Http\Controllers\Insights\DecisionSupportController;
use App\Services\Navigation\NavBadges;
use Database\Seeders\Demo\DemoGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Removes what DssDemoScenarioSeeder added, and nothing else: the bookings with its
 * marker email, the leave with its "(DSS demo)" reason, and the demo spa's stored
 * Decision Support recommendations (they are recomputed on the next page visit).
 */
class DssDemoScenarioRemoveSeeder extends Seeder
{
    public function run(): void
    {
        if (! DemoGuard::environmentAllowed($this->command, 'DssDemoScenarioRemoveSeeder')) {
            return;
        }

        [$spa] = DssDemoScenarioSeeder::target();

        [$bookings, $leave, $recs] = DB::transaction(function () use ($spa) {
            $ids = DB::table('bookings')->where('customer_email', DssDemoScenarioSeeder::MARKER_EMAIL)->pluck('id');
            foreach ($ids->chunk(1000) as $chunk) {
                DB::table('booking_consumptions')->whereIn('booking_id', $chunk)->delete();
                DB::table('ratings')->whereIn('booking_id', $chunk)->delete();
                DB::table('bookings')->whereIn('id', $chunk)->delete();
            }
            $leave = DB::table('leave_requests')->where('reason', 'like', DssDemoScenarioSeeder::LEAVE_MARKER.'%')->delete();
            $recs = $spa ? DB::table('dss_recommendations')->where('spa_id', $spa->id)->delete() : 0;

            return [$ids->count(), $leave, $recs];
        });

        if ($spa) {
            Cache::forget(DecisionSupportController::syncKey((int) $spa->id));
            NavBadges::bump((int) $spa->id);
        }

        $this->command?->info("DSS scenario removed: {$bookings} bookings, {$leave} leave, {$recs} recommendations.");
    }
}
