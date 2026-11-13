<?php

namespace Tests\Feature\Demo;

use App\Models\Spa;
use App\Models\User;
use Database\Seeders\Demo\DemoCatalog;
use Database\Seeders\DemoDataRemoveSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The production demo data (DemoDataSeeder / Remove): it makes every scenario
 * spa in the right state, and Remove takes away all of it and nothing else.
 * Seeding takes ~17 s on SQLite, so it runs once, in a single test.
 */
class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        config(['demo.password' => 'Demo-Password-For-Tests']);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_seed_makes_every_situation_and_remove_takes_only_demo_data(): void
    {
        $real = User::factory()->create(['email' => 'real.person@example.com']);

        $this->seed(DemoDataSeeder::class);

        $spa = fn (string $key) => Spa::withoutGlobalScopes()->where('name', DemoCatalog::spaName($key))->firstOrFail();

        $this->assertSame(count(DemoCatalog::SPAS), Spa::withoutGlobalScopes()->whereIn('name', DemoCatalog::spaNames())->count());
        $this->assertSame('premium', $spa('serenity')->currentPlan());
        $this->assertSame('basic', $spa('hilot')->currentPlan());
        $this->assertTrue($spa('lakbay')->isTrialing());
        $this->assertFalse($spa('tahimik')->hasAccess());
        $this->assertTrue($spa('banayad')->isInGracePeriod());
        $this->assertFalse($spa('ginhawa')->hasAccess());
        $this->assertTrue($spa('bagong')->canStartTrial());
        $this->assertSame('pending', $spa('kalinaw')->verification_status);
        $this->assertSame('rejected', $spa('payapa')->verification_status);
        $this->assertSame('unverified', $spa('unang')->verification_status);
        $this->assertSame(10, DB::table('staff')->where('spa_id', $spa('kalma')->id)->count());
        $this->assertSame(0, User::where('spa_id', $spa('luntian')->id)->whereNotNull('identity_verified_at')->count());

        $serenity = $spa('serenity')->id;
        $this->assertGreaterThan(1000, DB::table('bookings')->where('spa_id', $serenity)->count());
        $this->assertGreaterThan(0, DB::table('payroll_runs')->where('spa_id', $serenity)->where('status', 'released')->count());
        $this->assertGreaterThan(0, DB::table('booking_consumptions')->where('spa_id', $serenity)->count());
        $this->assertSame(0, DB::table('booking_consumptions')->where('has_shortfall', true)->count());
        $this->assertTrue(Storage::disk('local')->exists(DemoCatalog::FILE_DIR));

        $this->seed(DemoDataRemoveSeeder::class);

        $this->assertSame(0, DB::table('spas')->count());
        $this->assertSame(0, User::where('email', 'like', DemoCatalog::emailPattern())->count());
        $this->assertSame(0, DB::table('bookings')->count());
        $this->assertSame(0, DB::table('products')->count());
        $this->assertFalse(Storage::disk('local')->exists(DemoCatalog::FILE_DIR));
        $this->assertTrue(User::whereKey($real->id)->exists());
    }

    public function test_it_refuses_without_a_password_and_in_production_without_the_flag(): void
    {
        config(['demo.password' => '']);
        $this->seed(DemoDataSeeder::class);
        $this->assertSame(0, DB::table('spas')->count());

        config(['demo.password' => 'Demo-Password-For-Tests', 'demo.allow_production' => false]);
        $this->app['env'] = 'production';
        $this->artisan('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('spas')->count());
    }
}
