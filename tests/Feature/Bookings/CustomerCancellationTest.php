<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\BookingRefund;
use App\Models\OnlineReservationPayment;
use App\Models\User;
use App\Services\Bookings\CancellationNotAllowed;
use App\Services\Bookings\CustomerCancellation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\HomeVisitTestSeeder;
use Tests\Fixtures\IdentityVerificationTestSeeder;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Tests\TestCase;

/**
 * Customer cancellation rule (Nov 2026), CustomerCancellation:
 *   free until min(paid + 24h, start - 3h), once per rolling 30 days → full refund;
 *   otherwise the 20% downpayment is kept and anything above it comes back.
 */
class CustomerCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Booking $template;
    private CustomerCancellation $rule;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-14 10:00:00');
        Storage::fake('local');
        Mail::fake();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
        $this->seed(HomeVisitTestSeeder::class);

        $this->customer = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $this->template = Booking::where('customer_user_id', $this->customer->id)->firstOrFail();
        $this->rule = app(CustomerCancellation::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** An online booking of ₱1,000 starting at $start, paid at $paidAt ('down' = ₱200, 'full' = ₱1,000). */
    private function booking(string $start, string $paidAt, string $option = 'down'): Booking
    {
        $startAt = Carbon::parse($start);
        $paid = $option === 'full' ? 1000 : 200;

        $b = $this->template->replicate();
        $b->forceFill([
            'status' => 'reserved', 'booking_source' => 'online',
            'customer_user_id' => $this->customer->id,
            'total_amount' => 1000, 'amount_paid' => $paid, 'balance_amount' => 1000 - $paid,
            'payment_status' => $option === 'full' ? 'paid' : 'partially_paid',
            'appointment_date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $startAt->copy()->addHour()->format('H:i:s'),
            'cancelled_at' => null, 'cancelled_by' => null, 'free_cancellation' => false,
        ])->saveQuietly();

        OnlineReservationPayment::forceCreate([
            'user_id' => $this->customer->id, 'spa_id' => $b->spa_id, 'branch_id' => $b->branch_id,
            'customer_name' => 'Test', 'customer_email' => 'c@example.test', 'customer_phone' => '09171234567',
            'bookable_id' => 1, 'bookable_type' => 'treatment', 'bookable_name' => 'Massage',
            'full_amount' => 1000, 'downpayment_amount' => 200, 'payment_option' => $option === 'full' ? 'full' : 'downpayment',
            'amount_charged' => $paid, 'service_type' => $b->service_type,
            'appointment_date' => $startAt->toDateString(), 'start_time' => $startAt->format('H:i:s'),
            'payment_status' => 'paid', 'reservation_status' => 'reserved',
            'paid_at' => Carbon::parse($paidAt), 'booking_id' => $b->id,
        ]);

        return $b->fresh();
    }

    public function test_free_inside_the_window_refunds_everything(): void
    {
        $b = $this->booking('2026-11-20 14:00', '2026-11-14 09:00');

        $quote = $this->rule->quote($b);
        $this->assertTrue($quote['free']);
        $this->assertSame('2026-11-15T09:00:00+08:00', Carbon::parse($quote['free_until'])->setTimezone('Asia/Manila')->toIso8601String());

        $this->rule->cancel($b, $this->customer, 'Booked the wrong day');

        $b->refresh();
        $this->assertSame('cancelled', $b->status);
        $this->assertSame('customer', $b->cancelled_by);
        $this->assertTrue($b->free_cancellation);
        $this->assertSame('200.00', $b->refund->amount);
        $this->assertSame(BookingRefund::KIND_FREE, $b->refund->kind);
        $this->assertSame(BookingRefund::STATUS_PENDING, $b->refund->status);
    }

    public function test_window_ends_three_hours_before_a_close_appointment(): void
    {
        // Paid 10 PM, appointment 9 AM next day: free until 6 AM, not 10 PM.
        Carbon::setTestNow('2026-11-14 22:30:00');
        $b = $this->booking('2026-11-15 09:00', '2026-11-14 22:00');
        $this->assertSame('2026-11-15 06:00', Carbon::parse($this->rule->quote($b)['free_until'])->format('Y-m-d H:i'));

        Carbon::setTestNow('2026-11-15 06:01:00');
        $quote = $this->rule->quote($b->fresh());
        $this->assertFalse($quote['free']);
        $this->assertSame(CustomerCancellation::NOT_FREE_CLOSED, $quote['reason']);
    }

    public function test_booked_less_than_three_hours_ahead_has_no_free_window(): void
    {
        $b = $this->booking('2026-11-14 12:00', '2026-11-14 09:30');

        $quote = $this->rule->quote($b);
        $this->assertFalse($quote['free']);
        $this->assertSame(CustomerCancellation::NOT_FREE_NONE, $quote['reason']);
        $this->assertNull($quote['free_until']);
    }

    public function test_late_cancel_of_a_full_payment_refunds_80_percent(): void
    {
        $b = $this->booking('2026-11-20 14:00', '2026-11-12 09:00', 'full');

        $quote = $this->rule->cancel($b, $this->customer);

        $this->assertSame(800.0, $quote['refund']);
        $this->assertSame(200.0, $quote['kept']);
        $this->assertSame(BookingRefund::KIND_LATE, $b->fresh()->refund->kind);
        $this->assertFalse($b->fresh()->free_cancellation);
    }

    public function test_late_cancel_of_a_downpayment_queues_no_refund(): void
    {
        $b = $this->booking('2026-11-20 14:00', '2026-11-12 09:00');

        $quote = $this->rule->cancel($b, $this->customer);

        $this->assertSame(0.0, $quote['refund']);
        $this->assertSame('cancelled', $b->fresh()->status);
        $this->assertNull($b->fresh()->refund);
    }

    public function test_one_free_cancellation_per_30_days(): void
    {
        $first = $this->booking('2026-11-20 14:00', '2026-11-14 09:00');
        $this->rule->cancel($first, $this->customer);

        $second = $this->booking('2026-11-21 14:00', '2026-11-14 09:30', 'full');
        $quote = $this->rule->quote($second);
        $this->assertFalse($quote['free']);
        $this->assertSame(CustomerCancellation::NOT_FREE_USED, $quote['reason']);
        $this->assertSame('2026-12-14', Carbon::parse($quote['free_again_on'])->toDateString());
        $this->assertSame(800.0, $quote['refund']);

        // 30 days later it is free again.
        Carbon::setTestNow('2026-12-14 10:00:01');
        $third = $this->booking('2026-12-20 14:00', '2026-12-14 09:00');
        $this->assertTrue($this->rule->quote($third)['free']);
    }

    public function test_cannot_cancel_twice_or_someone_elses_booking(): void
    {
        $b = $this->booking('2026-11-20 14:00', '2026-11-14 09:00', 'full');
        $this->rule->cancel($b, $this->customer);

        try {
            $this->rule->cancel($b, $this->customer);
            $this->fail('A second cancel went through.');
        } catch (CancellationNotAllowed) {
        }
        $this->assertSame(1, BookingRefund::where('booking_id', $b->id)->count());

        $other = $this->booking('2026-11-21 14:00', '2026-11-14 09:00');
        $stranger = User::where('id', '!=', $this->customer->id)->firstOrFail();
        $this->expectException(CancellationNotAllowed::class);
        $this->rule->cancel($other, $stranger);
    }

    public function test_started_walk_in_and_finished_bookings_cannot_be_cancelled(): void
    {
        $started = $this->booking('2026-11-14 09:30', '2026-11-13 09:00');
        $this->assertFalse($this->rule->quote($started)['can_cancel']);

        $walkIn = $this->booking('2026-11-20 14:00', '2026-11-14 09:00');
        $walkIn->forceFill(['booking_source' => 'walk_in'])->saveQuietly();
        $this->assertFalse($this->rule->quote($walkIn)['can_cancel']);

        $done = $this->booking('2026-11-20 14:00', '2026-11-14 09:00');
        $done->forceFill(['status' => 'completed'])->saveQuietly();
        $this->assertFalse($this->rule->quote($done)['can_cancel']);
    }

    public function test_old_booking_without_a_payment_row_falls_back_to_the_booking(): void
    {
        $b = $this->booking('2026-11-20 14:00', '2026-11-12 09:00', 'full');
        OnlineReservationPayment::where('booking_id', $b->id)->delete();
        $b->forceFill(['created_at' => '2026-11-10 09:00:00'])->saveQuietly();

        $quote = $this->rule->quote($b->fresh());
        $this->assertFalse($quote['free']);
        $this->assertSame(800.0, $quote['refund']);
    }

    public function test_customer_cancels_through_the_endpoint(): void
    {
        $b = $this->booking('2026-11-20 14:00', '2026-11-12 09:00', 'full');

        $this->actingAs($this->customer)
            ->postJson(route('customer.appointments.cancel', $b), ['reason' => 'Plans changed'])
            ->assertOk()
            ->assertJsonPath('quote.refund', 800)
            ->assertJsonPath('message', 'Booking cancelled. ₱800.00 will be refunded to you; the ₱200.00 reservation fee is kept.');

        $this->assertSame('Plans changed', $b->fresh()->cancel_reason);

        // A second try is refused with a message, not a 500.
        $this->actingAs($this->customer)
            ->postJson(route('customer.appointments.cancel', $b))
            ->assertStatus(422);
    }

    public function test_someone_elses_booking_is_not_found(): void
    {
        $b = $this->booking('2026-11-20 14:00', '2026-11-14 09:00');
        $stranger = User::where('id', '!=', $this->customer->id)->role('customer')->first()
            ?? tap(User::factory()->create())->assignRole('customer');

        $this->actingAs($stranger)
            ->postJson(route('customer.appointments.cancel', $b))
            ->assertNotFound();

        $this->assertSame('reserved', $b->fresh()->status);
    }

    public function test_my_appointments_sends_the_cancel_quote_and_refund_state(): void
    {
        $open = $this->booking('2026-11-20 14:00', '2026-11-14 09:00');
        $gone = $this->booking('2026-11-21 14:00', '2026-11-12 09:00', 'full');
        $this->rule->cancel($gone, $this->customer);

        $rows = collect($this->actingAs($this->customer)->getJson(route('customer.appointments'))->assertOk()->json())
            ->keyBy('id');

        $this->assertTrue($rows[$open->id]['cancel']['can_cancel']);
        $this->assertTrue($rows[$open->id]['cancel']['free']);
        $this->assertNull($rows[$open->id]['cancelled']);

        $this->assertFalse($rows[$gone->id]['cancel']['can_cancel']);
        $this->assertSame('customer', $rows[$gone->id]['cancelled']['by']);
        $this->assertSame('pending', $rows[$gone->id]['cancelled']['refund_status']);
        $this->assertEquals(800, $rows[$gone->id]['cancelled']['refund_amount']);
        $this->assertEquals(200, $rows[$gone->id]['cancelled']['kept']);
    }

    public function test_admin_marks_a_refund_sent_and_the_customer_sees_it(): void
    {
        $b = $this->booking('2026-11-20 14:00', '2026-11-12 09:00', 'full');
        $this->rule->cancel($b, $this->customer);
        $refund = $b->fresh()->refund;

        $admin = \Database\Factories\PayrollUserFactory::new()->create();
        $admin->assignRole('admin');

        $this->withoutVite();
        $this->actingAs($admin)->get(route('admin.refunds.index'))
            ->assertOk()
            ->assertSee('₱800.00')
            ->assertSee('Mark as sent');

        // A reference is required.
        $this->actingAs($admin)->post(route('admin.refunds.sent', $refund), ['payment_method' => 'gcash'])
            ->assertSessionHasErrorsIn('refund', 'reference');

        $this->actingAs($admin)->post(route('admin.refunds.sent', $refund), [
            'payment_method' => 'gcash', 'reference' => 'GC-123456',
        ])->assertRedirect(route('admin.refunds.index', ['tab' => 'pending']));

        $refund->refresh();
        $this->assertSame('sent', $refund->status);
        $this->assertSame($admin->id, $refund->processed_by);

        $row = collect($this->actingAs($this->customer)->getJson(route('customer.appointments'))->json())
            ->firstWhere('id', $b->id);
        $this->assertSame('sent', $row['cancelled']['refund_status']);

        // Customers and spa owners can't open the queue.
        $this->actingAs($this->customer)->get(route('admin.refunds.index'))->assertForbidden();
    }

    public function test_kept_deposits_take_refunds_off(): void
    {
        $spaId = (int) $this->template->spa_id;
        $branchId = (int) $this->template->branch_id;
        $before = \App\Services\Finance\BookingRevenue::keptDeposits($spaId, $branchId);

        // Free: ₱200 paid, ₱200 back → nothing kept, not counted.
        $this->rule->cancel($this->booking('2026-11-20 14:00', '2026-11-14 09:00'), $this->customer);
        // Late, full payment: ₱1,000 paid, ₱800 back → ₱200 kept.
        $this->rule->cancel($this->booking('2026-11-21 14:00', '2026-11-12 09:00', 'full'), $this->customer);

        $after = \App\Services\Finance\BookingRevenue::keptDeposits($spaId, $branchId);
        $this->assertEqualsWithDelta(200.0, $after['amount'] - $before['amount'], 0.001);
        $this->assertSame(1, $after['count'] - $before['count']);
    }

    public function test_the_app_cancels_through_the_api(): void
    {
        $b = $this->booking('2026-11-20 14:00', '2026-11-14 09:00');

        \Laravel\Sanctum\Sanctum::actingAs($this->customer);

        $row = collect($this->getJson('/api/my-appointments')->assertOk()->json())->firstWhere('id', $b->id);
        $this->assertTrue($row['cancel']['free']);

        $this->postJson("/api/appointments/{$b->id}/cancel", ['reason' => 'Wrong day'])
            ->assertOk()
            ->assertJsonPath('quote.free', true)
            ->assertJsonPath('message', 'Booking cancelled. ₱200.00 will be refunded to you.');

        $this->assertSame('cancelled', $b->fresh()->status);
    }

    // ── #15 emails, #16 spa-side refunds ────────────────────────────────────

    public function test_customer_cancel_emails_the_customer_and_marking_sent_emails_again(): void
    {
        $b = $this->booking('2026-11-20 14:00', '2026-11-12 09:00', 'full');

        $this->actingAs($this->customer)
            ->postJson(route('customer.appointments.cancel', $b))->assertOk();

        Mail::assertSent(\App\Mail\BookingCancelledMail::class, fn ($m) => $m->by === 'customer'
            && $m->refund === 800.0 && $m->kept === 200.0 && $m->hasTo($b->customer_email));

        $admin = \Database\Factories\PayrollUserFactory::new()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->post(route('admin.refunds.sent', $b->fresh()->refund), [
            'payment_method' => 'gcash', 'reference' => 'GC-1',
        ]);

        Mail::assertSent(\App\Mail\RefundSentMail::class, fn ($m) => $m->refund->reference === 'GC-1');
    }

    public function test_the_spa_cancelling_queues_a_full_refund_and_emails_the_customer(): void
    {
        $b = $this->booking('2026-11-20 14:00', '2026-11-12 09:00', 'full');
        $manager = User::where('email', 'manager.a@levictas.test')->firstOrFail();

        $this->withoutVite();
        $this->actingAs($manager)->from(route('appointments.index'))
            ->put(route('appointments.updateStatus', $b), ['status' => 'cancelled'])
            ->assertSessionHas('success', 'Appointment cancelled. Levictas will refund the customer ₱1,000.00.');

        $b->refresh();
        $this->assertSame('staff', $b->cancelled_by);
        $this->assertSame(\App\Models\BookingRefund::KIND_SPA, $b->refund->kind);
        $this->assertSame('1000.00', $b->refund->amount);
        Mail::assertSent(\App\Mail\BookingCancelledMail::class, fn ($m) => $m->by === 'staff' && $m->refund === 1000.0);

        // The customer sees who cancelled and the refund.
        $row = collect($this->actingAs($this->customer)->getJson(route('customer.appointments'))->json())
            ->firstWhere('id', $b->id);
        $this->assertSame('staff', $row['cancelled']['by']);
        $this->assertNull($row['cancelled']['kept']);
    }

    public function test_a_no_show_refunds_80_percent_of_a_full_payment_only(): void
    {
        Carbon::setTestNow('2026-11-20 14:30:00');
        $manager = User::where('email', 'manager.a@levictas.test')->firstOrFail();
        $full = $this->booking('2026-11-20 14:00', '2026-11-12 09:00', 'full');
        $down = $this->booking('2026-11-20 14:00', '2026-11-12 09:00');

        foreach ([$full, $down] as $b) {
            $b->forceFill(['status' => 'pending'])->saveQuietly();
            $this->actingAs($manager)->from(route('appointments.index'))
                ->put(route('appointments.updateStatus', $b), ['status' => Booking::NO_SHOW])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame('800.00', $full->fresh()->refund->amount);
        $this->assertSame(\App\Models\BookingRefund::KIND_NO_SHOW, $full->fresh()->refund->kind);
        $this->assertNull($down->fresh()->refund);
        Mail::assertNotSent(\App\Mail\BookingCancelledMail::class);

        $row = collect($this->actingAs($this->customer)->getJson(route('customer.appointments'))->json())
            ->firstWhere('id', $full->id);
        $this->assertSame('no_show', $row['cancelled']['by']);
        $this->assertEquals(200, $row['cancelled']['kept']);
    }

    public function test_the_app_gets_the_free_again_date_with_its_booked_dates(): void
    {
        $this->rule->cancel($this->booking('2026-11-20 14:00', '2026-11-14 09:00'), $this->customer);

        \Laravel\Sanctum\Sanctum::actingAs($this->customer);
        $again = $this->getJson('/api/bookings/online/my-dates')->assertOk()->json('free_again_on');
        $this->assertSame('2026-12-14', Carbon::parse($again)->toDateString());
    }
}
