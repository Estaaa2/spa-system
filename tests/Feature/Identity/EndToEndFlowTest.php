<?php

namespace Tests\Feature\Identity;

use App\Mail\IdentityVerificationResult;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\IdentityVerification;
use App\Models\OperatingHours;
use App\Models\Treatment;
use App\Models\User;
use Database\Factories\PayrollUserFactory;
use Database\Seeders\IdentityVerificationTestSeeder;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 8 — the whole feature end to end, the way the defense demo runs it:
 *
 *   submit → approve → home-service slots open → photo change → slots close
 *   → reject → resubmit; then a home visit: customer card + code, wrong code,
 *   rate limit, right code. Files are checked on disk at each step.
 */
class EndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_CHECKS = ['same_person', 'name_matches', 'same_id', 'valid_id'];

    private Branch $branch;
    private Treatment $treatment;
    private User $customer;
    private User $admin;
    private User $paolo;
    private string $date = '2026-10-31';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-29 08:00:00');
        Storage::fake('local');
        Mail::fake();
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);

        $this->branch = Branch::where('name', 'Test Branch A')->firstOrFail();
        $this->treatment = Treatment::withoutGlobalScopes()->where('branch_id', $this->branch->id)->firstOrFail();
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            OperatingHours::create(['branch_id' => $this->branch->id, 'day_of_week' => $day, 'opening_time' => '09:00:00', 'closing_time' => '21:00:00', 'is_closed' => false]);
        }

        $this->customer = PayrollUserFactory::new()->create(['first_name' => 'Cara', 'last_name' => 'Customer', 'email' => 'cara@levictas.test', 'phone' => '09171234567']);
        $this->customer->assignRole('customer');
        $this->admin = PayrollUserFactory::new()->create(['first_name' => 'Ada', 'last_name' => 'Admin']);
        $this->admin->assignRole('admin');
        $this->paolo = $this->user('idv.paolo@levictas.test');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function photo(string $name = 'p.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 600, 600);
    }

    /** Vera and Rhea (the verified providers) are busy 10:00–11:00. */
    private function verifiedProvidersBusyAtTen(): void
    {
        foreach (['idv.vera@levictas.test', 'idv.rhea@levictas.test'] as $email) {
            Booking::create([
                'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id, 'therapist_id' => $this->user($email)->id,
                'status' => 'reserved', 'service_type' => 'in_branch', 'booking_source' => 'walk_in',
                'treatment' => 'treatment_'.$this->treatment->id, 'customer_name' => 'Busy',
                'appointment_date' => $this->date, 'start_time' => '10:00:00', 'end_time' => '11:00:00',
            ]);
        }
    }

    private function slotAtTen(string $serviceType): array
    {
        $data = $this->actingAs($this->customer)->getJson('/bookings/online/available-slots?'.http_build_query([
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id,
            'treatment' => 'treatment_'.$this->treatment->id, 'appointment_date' => $this->date,
            'service_type' => $serviceType,
        ]))->assertOk()->json();

        return collect($data['slots'])->firstWhere('time', '10:00');
    }

    private function submitAsPaolo(): IdentityVerification
    {
        $this->actingAs($this->paolo)->from(route('profile.edit'))
            ->post(route('profile.identity.store'), [
                'id_type' => 'philid', 'id_image' => $this->photo('id.jpg'), 'selfie' => $this->photo('selfie.jpg'), 'consent' => '1',
            ])->assertSessionHasNoErrors();

        return IdentityVerification::where('user_id', $this->paolo->id)->latest('id')->firstOrFail();
    }

    private function filesOf(IdentityVerification $row): array
    {
        return array_values(array_filter([$row->id_image_path, $row->selfie_path]));
    }

    // ── Verification lifecycle ───────────────────────────────────────────────

    public function test_submit_approve_photo_change_reject_resubmit(): void
    {
        $this->verifiedProvidersBusyAtTen();
        $disk = Storage::disk('local');

        // Only unverified Paolo is free at 10:00: no home service, in-branch fine.
        $this->assertFalse($this->slotAtTen('in_home')['available']);
        $this->assertTrue($this->slotAtTen('in_branch')['available']);

        // 1. Photo + submission.
        $this->actingAs($this->paolo)->from(route('profile.edit'))
            ->post(route('profile.photo.update'), ['photo' => $this->photo()])->assertSessionHasNoErrors();
        $row = $this->submitAsPaolo();
        $files = $this->filesOf($row);
        $this->assertCount(2, $files);
        foreach ($files as $path) {
            $disk->assertExists($path);
        }

        // Private: nobody reaches the files by a public URL, staff of the spa can't open them.
        $this->assertContains($this->get('/storage/'.$files[0])->status(), [403, 404]);
        $this->actingAs($this->user('manager.a@levictas.test'))
            ->get(route('admin.identity-verifications.files.show', [$row, 'id']))->assertForbidden();

        // 2. Admin approves → files gone, email sent, home service opens.
        $this->actingAs($this->admin)->post(route('admin.identity-verifications.approve', $row), ['checks' => self::ALL_CHECKS])
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->paolo->fresh()->isIdentityVerified());
        foreach ($files as $path) {
            $disk->assertMissing($path);
        }
        Mail::assertSent(IdentityVerificationResult::class);
        $this->assertTrue($this->slotAtTen('in_home')['available']);

        // 3. New photo → unverified → home service closes again; in-branch unaffected.
        $this->actingAs($this->paolo)->from(route('profile.edit'))
            ->post(route('profile.photo.update'), ['photo' => $this->photo('new.jpg')]);
        $this->assertFalse($this->paolo->fresh()->isIdentityVerified());
        $this->assertFalse($this->slotAtTen('in_home')['available']);
        $this->assertTrue($this->slotAtTen('in_branch')['available']);

        // 4. Reject with a reason → files gone → resubmit is accepted.
        $second = $this->submitAsPaolo();
        $this->actingAs($this->admin)->post(route('admin.identity-verifications.reject', $second), [
            'rejection_reason' => 'The ID photo is blurry, so the name cannot be read.',
        ])->assertSessionHasNoErrors();
        foreach ($this->filesOf($second->fresh()) as $path) {
            $disk->assertMissing($path);
        }
        $this->actingAs($this->paolo)->get(route('profile.edit'))->assertSee('The ID photo is blurry');

        $third = $this->submitAsPaolo();
        $this->assertSame(IdentityVerification::STATUS_PENDING, $third->status);

        // 5. The app sees the same state as the web.
        Sanctum::actingAs($this->paolo->fresh());
        $this->getJson('/api/me/identity')->assertOk()->assertJsonPath('identity.state', 'pending');
    }

    // ── Home visit ───────────────────────────────────────────────────────────

    public function test_home_visit_card_code_and_arrival(): void
    {
        Carbon::setTestNow($this->date.' 09:00:00');
        $vera = $this->user('idv.vera@levictas.test');

        $booking = Booking::create([
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id, 'customer_user_id' => $this->customer->id,
            'therapist_id' => $vera->id, 'status' => 'reserved', 'service_type' => 'in_home', 'booking_source' => 'online',
            'treatment' => 'treatment_'.$this->treatment->id, 'customer_name' => 'Cara Customer', 'customer_email' => 'cara@levictas.test',
            'customer_address' => 'Imus, Cavite', 'appointment_date' => $this->date, 'start_time' => '10:00:00', 'end_time' => '11:00:00',
        ]);
        $code = $booking->arrival_code;

        // Customer: card + code (web and app give the same code).
        $row = collect($this->actingAs($this->customer)->getJson('/my-appointments')->assertOk()->json())->firstWhere('id', $booking->id);
        $this->assertSame('Vera Verified (test)', $row['therapist_card']['name']);
        $this->assertTrue($row['therapist_card']['verified']);
        $this->assertSame($code, $row['arrival_code']);

        // Therapist: never sees the code.
        $this->actingAs($vera)->get(route('dashboard'))->assertOk()->assertSee('Enter arrival code')->assertDontSee('>'.$code.'<', false);

        // Right code from the customer → arrival recorded; status still follows the clock.
        $wrong = $code === '0000' ? '1111' : '0000';
        $this->actingAs($vera)->from(route('dashboard'))->post(route('appointments.arrival', $booking), ['arrival_code' => $wrong])
            ->assertSessionHasErrorsIn('arrival', 'arrival_code');
        $this->assertSame(1, $booking->fresh()->arrival_attempts);
        $this->actingAs($vera)->from(route('dashboard'))->post(route('appointments.arrival', $booking), ['arrival_code' => $code])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($booking->fresh()->arrival_confirmed_at);
        $this->assertSame('reserved', $booking->fresh()->status);
    }

    public function test_wrong_codes_lock_then_rate_limit_then_branch_confirms(): void
    {
        Carbon::setTestNow($this->date.' 09:00:00');
        $vera = $this->user('idv.vera@levictas.test');
        $booking = Booking::create([
            'spa_id' => $this->branch->spa_id, 'branch_id' => $this->branch->id, 'customer_user_id' => $this->customer->id,
            'therapist_id' => $vera->id, 'status' => 'reserved', 'service_type' => 'in_home', 'booking_source' => 'online',
            'treatment' => 'treatment_'.$this->treatment->id, 'customer_name' => 'Cara Customer', 'customer_email' => 'cara@levictas.test',
            'customer_address' => 'Imus, Cavite', 'appointment_date' => $this->date, 'start_time' => '10:00:00', 'end_time' => '11:00:00',
        ]);
        $code = $booking->arrival_code;
        $wrong = $code === '0000' ? '1111' : '0000';

        // 10 tries in a minute: 5 wrong lock the code, the rest are still allowed through.
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($vera)->from(route('dashboard'))->post(route('appointments.arrival', $booking), ['arrival_code' => $wrong]);
        }
        $this->assertNotNull($booking->fresh()->arrival_locked_at);

        // 11th in the same minute: rate-limited before it reaches the code check.
        $this->actingAs($vera)->from(route('dashboard'))->post(route('appointments.arrival', $booking), ['arrival_code' => $code])
            ->assertStatus(429);
        $this->assertNull($booking->fresh()->arrival_confirmed_at);

        // The branch confirms by hand, with a reason.
        $manager = $this->user('manager.a@levictas.test');
        $this->actingAs($manager)->from(route('appointments.index'))
            ->post(route('appointments.arrival.manual', $booking), ['arrival_reason' => 'Code locked; called the customer.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('manual', $booking->fresh()->arrival_method);
    }

    // ── Regression found while writing the guide ─────────────────────────────

    public function test_reassignment_review_sends_the_booking_id_for_the_verified_filter(): void
    {
        $html = $this->actingAs($this->user('manager.a@levictas.test'))->get(route('appointments.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-booking-id="${r.booking_id}"', $html);
        $this->assertStringContainsString('booking_id: d.bookingId', $html);
    }
}
