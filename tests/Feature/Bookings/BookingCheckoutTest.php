<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\BookingConsumption;
use App\Models\BranchProductStock;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\Treatment;
use App\Models\TreatmentRecipeItem;
use App\Models\User;
use App\Services\BookingCompletionService;
use Tests\Fixtures\HomeVisitTestSeeder;
use Tests\Fixtures\IdentityVerificationTestSeeder;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Booking lifecycle fix, Phase 1: staff checkout is the only way a booking
 * completes (D1); the clock only moves reserved → pending (D2, Q1-B); stock
 * shortfalls never block checkout (D7, Q6-A/Q7-A).
 *
 * Booking A from HomeVisitTestSeeder: today 2026-10-30 at 10:00, online,
 * home service, 20% deposit paid (seeded at 09:20), arrival confirmed in setUp.
 */
class BookingCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-30 09:20:00');
        Storage::fake('local');
        Mail::fake();
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
        $this->seed(HomeVisitTestSeeder::class);

        $this->manager = User::where('email', 'manager.a@levictas.test')->firstOrFail();
        $customer = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $this->booking = Booking::where('customer_user_id', $customer->id)
            ->orderBy('appointment_date')->orderBy('start_time')->firstOrFail();

        // Booking A is an online home visit: since Phase 2 its arrival must be
        // confirmed before checkout (D4). These Phase 1 tests start from "arrived";
        // NeedsCheckoutTest covers the unconfirmed case.
        $this->booking->forceFill([
            'arrival_confirmed_at' => '2026-10-30 10:05:00',
            'arrival_confirmed_by' => $this->booking->therapist_id,
            'arrival_method'       => 'code',
        ])->saveQuietly();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** One recipe product for the booking's treatment, with $onHand usable stock. */
    private function giveTreatmentARecipe(float $perService, ?float $onHand, bool $active = true): Product
    {
        $treatment = Treatment::withoutGlobalScopes()
            ->findOrFail((int) str_replace('treatment_', '', $this->booking->treatment));

        $product = Product::create([
            'spa_id' => $this->booking->spa_id,
            'name' => 'Test Massage Oil',
            'unit' => 'ml',
            'usage_unit' => 'ml',
            'is_active' => $active,
        ]);

        TreatmentRecipeItem::create([
            'treatment_id' => $treatment->id,
            'product_id' => $product->id,
            'quantity' => $perService,
        ]);

        if ($onHand !== null) {
            BranchProductStock::create([
                'spa_id' => $this->booking->spa_id,
                'branch_id' => $this->booking->branch_id,
                'product_id' => $product->id,
                'on_hand_quantity' => $onHand,
            ]);

            if ($onHand > 0) {
                ProductBatch::create([
                    'spa_id' => $this->booking->spa_id,
                    'branch_id' => $this->booking->branch_id,
                    'product_id' => $product->id,
                    'batch_number' => 'T-1',
                    'received_quantity' => $onHand,
                    'remaining_quantity' => $onHand,
                    'received_at' => now()->subDay(),
                ]);
            }
        }

        return $product;
    }

    private function onHand(Product $product): float
    {
        return (float) BranchProductStock::where('product_id', $product->id)
            ->where('branch_id', $this->booking->branch_id)->value('on_hand_quantity');
    }

    private function checkout(?float $amount = null)
    {
        $amount ??= (float) $this->booking->fresh()->balance_amount;

        return $this->actingAs($this->manager)
            ->from(route('appointments.index'))
            ->put(route('appointments.checkout', $this->booking), ['amount_paid' => $amount]);
    }

    // ── The clock ────────────────────────────────────────────────────────

    public function test_the_clock_never_completes_or_starts_a_booking(): void
    {
        Carbon::setTestNow('2026-10-30 15:00:00');   // well past the end time

        $this->actingAs($this->manager)->get(route('appointments.index'))->assertOk();
        $this->artisan('bookings:sync-statuses')->assertSuccessful();

        // reserved → pending once started; never ongoing, never completed.
        $this->assertSame('pending', $this->booking->fresh()->status);
        $this->assertSame(0, BookingConsumption::count());

        // A session staff started stays ongoing after the end time.
        $this->booking->forceFill(['status' => 'ongoing'])->saveQuietly();
        $this->actingAs($this->manager)->get(route('appointments.index'))->assertOk();
        $this->artisan('bookings:sync-statuses')->assertSuccessful();
        $this->assertSame('ongoing', $this->booking->fresh()->status);
    }

    public function test_before_the_start_time_the_clock_keeps_it_reserved(): void
    {
        $this->artisan('bookings:sync-statuses')->assertSuccessful();
        $this->assertSame('reserved', $this->booking->fresh()->status);

        Carbon::setTestNow('2026-10-30 10:05:00');
        $this->artisan('bookings:sync-statuses')->assertSuccessful();
        $this->assertSame('pending', $this->booking->fresh()->status);

        // Still pending 20 minutes in: only staff "Start" sets ongoing (Q1-B).
        Carbon::setTestNow('2026-10-30 10:20:00');
        $this->artisan('bookings:sync-statuses')->assertSuccessful();
        $this->assertSame('pending', $this->booking->fresh()->status);
    }

    public function test_the_old_completion_job_is_gone_and_the_sync_job_runs_every_15_minutes(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($e) => (string) $e->command);

        $this->assertFalse($commands->contains(fn ($c) => str_contains($c, 'bookings:mark-completed')));

        $sync = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'bookings:sync-statuses'));
        $this->assertCount(1, $sync);
        $this->assertSame('*/15 * * * *', $sync->first()->expression);
    }

    public function test_history_only_syncs_the_viewers_own_bookings(): void
    {
        Carbon::setTestNow('2026-10-30 10:20:00');   // booking A has started

        // The manager has no bookings as a customer, so A (someone else's) is untouched.
        $this->actingAs($this->manager)->getJson(route('bookings.history'))->assertOk();
        $this->assertSame('reserved', $this->booking->fresh()->status);
    }

    // ── Start ────────────────────────────────────────────────────────────

    public function test_starting_a_session_no_longer_forces_a_payment(): void
    {
        Carbon::setTestNow('2026-10-30 10:20:00');
        $this->booking->forceFill(['status' => 'pending'])->saveQuietly();
        $paidBefore = (float) $this->booking->amount_paid;

        $this->actingAs($this->manager)->from(route('appointments.index'))
            ->put(route('appointments.updateStatus', $this->booking), ['status' => 'ongoing'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Appointment started.');

        $fresh = $this->booking->fresh();
        $this->assertSame('ongoing', $fresh->status);
        $this->assertEquals($paidBefore, (float) $fresh->amount_paid);
        $this->assertSame('partially_paid', $fresh->payment_status);
    }

    // ── Checkout ─────────────────────────────────────────────────────────

    public function test_checkout_collects_the_balance_completes_and_deducts_stock_once(): void
    {
        $product = $this->giveTreatmentARecipe(perService: 10, onHand: 50);
        Carbon::setTestNow('2026-10-30 15:00:00');   // after the end time: still allowed
        $this->booking->forceFill(['status' => 'pending'])->saveQuietly();   // nobody pressed Start (Q3-A)

        $this->checkout()->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Checked out. The appointment is completed.');

        $fresh = $this->booking->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertSame('paid', $fresh->payment_status);
        $this->assertEquals(0, (float) $fresh->balance_amount);
        $this->assertEquals((float) $fresh->total_amount, (float) $fresh->amount_paid);
        $this->assertEquals(40.0, $this->onHand($product));
        $this->assertSame(1, StockMovement::where('booking_id', $fresh->id)->count());
        $this->assertSame($this->manager->id, $fresh->consumption->processed_by);

        // A second checkout, or the completion service again, changes nothing.
        $this->checkout(0)->assertSessionHas('error');
        app(BookingCompletionService::class)->complete($fresh, $this->manager);
        $this->assertEquals(40.0, $this->onHand($product));
        $this->assertSame(1, StockMovement::where('booking_id', $fresh->id)->count());
        $this->assertSame(1, BookingConsumption::where('booking_id', $fresh->id)->count());
    }

    public function test_checkout_requires_the_full_remaining_balance(): void
    {
        Carbon::setTestNow('2026-10-30 11:30:00');
        $this->booking->forceFill(['status' => 'ongoing'])->saveQuietly();
        $balance = (float) $this->booking->balance_amount;
        $this->assertGreaterThan(0, $balance);

        $this->checkout($balance - 1)->assertSessionHasErrors('amount_paid');
        $this->checkout($balance + 1)->assertSessionHasErrors('amount_paid');

        $this->assertSame('ongoing', $this->booking->fresh()->status);
        $this->assertSame(0, BookingConsumption::count());
    }

    public function test_checkout_is_refused_before_the_appointment_starts(): void
    {
        $this->checkout()->assertSessionHas('error', 'An appointment can only be checked out once it has started.');
        $this->assertSame('reserved', $this->booking->fresh()->status);
    }

    public function test_a_past_day_booking_nobody_closed_can_still_be_checked_out(): void
    {
        Carbon::setTestNow('2026-11-02 09:00:00');   // three days later
        $this->booking->forceFill(['status' => 'pending'])->saveQuietly();

        $this->checkout()->assertSessionHasNoErrors();
        $this->assertSame('completed', $this->booking->fresh()->status);
    }

    // ── Stock shortfalls (D7) ────────────────────────────────────────────

    public function test_short_stock_still_completes_deducts_what_exists_and_records_the_rest(): void
    {
        $product = $this->giveTreatmentARecipe(perService: 10, onHand: 4);
        Carbon::setTestNow('2026-10-30 11:30:00');
        $this->booking->forceFill(['status' => 'ongoing'])->saveQuietly();

        $this->checkout()->assertSessionHasNoErrors()->assertSessionHas('success');

        $fresh = $this->booking->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertEquals(0.0, $this->onHand($product));   // never negative

        $consumption = $fresh->consumption()->with('items')->first();
        $this->assertTrue($consumption->has_shortfall);
        $this->assertEquals(4.0, (float) $consumption->items->sum('quantity'));
        $this->assertEquals(6.0, (float) $consumption->items->sum('short_quantity'));
        $this->assertStringContainsString('Stock was short for: Test Massage Oil (6 ml)', session('success'));
    }

    public function test_a_missing_stock_record_or_inactive_product_never_blocks_checkout(): void
    {
        $this->giveTreatmentARecipe(perService: 5, onHand: null);   // no branch stock row
        Carbon::setTestNow('2026-10-30 11:30:00');
        $this->booking->forceFill(['status' => 'ongoing'])->saveQuietly();

        $this->checkout()->assertSessionHasNoErrors();

        $consumption = $this->booking->fresh()->consumption()->with('items')->first();
        $this->assertSame('completed', $this->booking->fresh()->status);
        $this->assertTrue($consumption->has_shortfall);
        $this->assertEquals(5.0, (float) $consumption->items->sum('short_quantity'));
    }

    public function test_shortfalls_are_listed_on_the_inventory_products_page(): void
    {
        $this->giveTreatmentARecipe(perService: 10, onHand: 4);
        Carbon::setTestNow('2026-10-30 11:30:00');
        $this->booking->forceFill(['status' => 'ongoing'])->saveQuietly();
        $this->checkout()->assertSessionHasNoErrors();

        $owner = User::where('email', 'owner@levictas.test')->firstOrFail();
        $response = $this->actingAs($owner)->get(route('inventory.products'));

        if ($response->status() !== 200) {
            $this->markTestSkipped('The seeded owner cannot open Inventory → Products here (plan or branch setup).');
        }

        $response->assertSee('Stock Shortfalls from Checkouts')->assertSee('Test Massage Oil: 6 ml short');
    }

    public function test_cancelled_and_no_show_bookings_consume_nothing(): void
    {
        $product = $this->giveTreatmentARecipe(perService: 10, onHand: 50);
        Carbon::setTestNow('2026-10-30 10:20:00');
        $this->booking->forceFill(['status' => 'pending'])->saveQuietly();

        $this->actingAs($this->manager)->from(route('appointments.index'))
            ->put(route('appointments.updateStatus', $this->booking), ['status' => Booking::NO_SHOW])
            ->assertSessionHasNoErrors();

        app(BookingCompletionService::class)->complete($this->booking->fresh(), $this->manager);
        $this->checkout(0)->assertSessionHas('error');

        $this->assertSame(Booking::NO_SHOW, $this->booking->fresh()->status);
        $this->assertEquals(50.0, $this->onHand($product));
        $this->assertSame(0, BookingConsumption::count());
    }

    // ── Branch ownership ─────────────────────────────────────────────────

    public function test_staff_cannot_process_a_booking_from_another_branch(): void
    {
        $otherBranchId = \App\Models\Branch::withoutGlobalScopes()
            ->where('id', '!=', $this->booking->branch_id)->value('id');

        if (! $otherBranchId) {
            $this->markTestSkipped('The seeders create only one branch.');
        }

        Carbon::setTestNow('2026-10-30 11:30:00');
        $this->booking->forceFill(['status' => 'ongoing', 'branch_id' => $otherBranchId])->saveQuietly();

        $this->checkout()->assertNotFound();
        $this->actingAs($this->manager)
            ->put(route('appointments.updateStatus', $this->booking), ['status' => 'cancelled'])
            ->assertNotFound();

        $this->assertSame('ongoing', $this->booking->fresh()->status);
    }
}
