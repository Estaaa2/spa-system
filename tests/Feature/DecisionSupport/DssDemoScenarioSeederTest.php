<?php

namespace Tests\Feature\DecisionSupport;

use App\Models\DssRecommendation;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\DssDemoScenarioRemoveSeeder;
use Database\Seeders\DssDemoScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** DSS revamp: the demo scenario makes every rule show, and Remove takes back only its own rows. */
class DssDemoScenarioSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_scenario_triggers_every_rule_and_remove_leaves_the_demo_data(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        config(['demo.password' => 'demo-test-password-123']);

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
        $demoBookings = DB::table('bookings')->count();

        $this->seed(DssDemoScenarioSeeder::class);

        [$spa] = DssDemoScenarioSeeder::target();
        $rules = DssRecommendation::where('spa_id', $spa->id)->open()->pluck('rule_key')->unique()->values()->all();
        foreach (['r1_staffing', 'r2_hire', 'r6_expansion'] as $rule) {
            $this->assertContains($rule, $rules, "$rule should show on the busy demo branch");
        }
        $this->assertGreaterThan($demoBookings, DB::table('bookings')->count());

        // Running it twice does not add more.
        $after = DB::table('bookings')->count();
        $this->seed(DssDemoScenarioSeeder::class);
        $this->assertSame($after, DB::table('bookings')->count());

        $this->seed(DssDemoScenarioRemoveSeeder::class);
        $this->assertSame($demoBookings, DB::table('bookings')->count());
        $this->assertSame(0, DB::table('leave_requests')->where('reason', 'like', '(DSS demo)%')->count());
        $this->assertSame(0, DssRecommendation::where('spa_id', $spa->id)->count());
    }
}
