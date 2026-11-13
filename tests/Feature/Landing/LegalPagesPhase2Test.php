<?php

namespace Tests\Feature\Landing;

use App\Models\Applicant;
use App\Models\Branch;
use App\Models\JobPosting;
use App\Models\OnlineReservationPayment;
use App\Models\OperatingHours;
use App\Models\PendingRegistration;
use App\Models\Treatment;
use App\Models\User;
use App\Services\PendingRegistrationService;
use App\Support\Identity\ConsentNotice;
use App\Support\Legal\Contact;
use App\Support\Legal\PrivacyPolicy;
use App\Support\Legal\RefundPolicy;
use App\Support\Legal\Terms;
use Database\Factories\PayrollUserFactory;
use Tests\Fixtures\HomeVisitTestSeeder;
use Tests\Fixtures\IdentityVerificationTestSeeder;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Landing footer Phase 2: the legal pages, one source for the Terms (D8),
 * consent wording (Q9-A), recording acceptance (Q10-A), the job-application
 * consent (Q5-A) and the shared privacy contact (Q2-A).
 */
class LegalPagesPhase2Test extends TestCase
{
    use RefreshDatabase;

    private const ROUTES = ['legal.terms', 'legal.privacy', 'legal.refund'];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-29 08:00:00');
        Storage::fake('local');
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
        $this->seed(HomeVisitTestSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function customer(): User
    {
        return User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
    }

    private function user(string $key): User
    {
        return User::where('email', "{$key}@levictas.test")->firstOrFail();
    }

    /** First sentence of a Terms section, with tokens filled — the text a page must show. */
    private function termsText(string $id, int $block = 0): string
    {
        $text = Terms::sections([$id])[0]['blocks'][$block];

        return is_array($text) ? $text['list'][0] : $text;
    }

    // ── Pages and routes ─────────────────────────────────────────────────────

    public function test_legal_routes_carry_no_auth_or_landing_redirect(): void
    {
        foreach (self::ROUTES as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertSame(['web'], $route->gatherMiddleware(), $name);
        }

        $this->assertSame('/terms-and-conditions', route('legal.terms', [], false));
        $this->assertSame('/privacy-policy', route('legal.privacy', [], false));
        $this->assertSame('/refund-policy', route('legal.refund', [], false));
    }

    public function test_every_role_can_open_every_legal_page(): void
    {
        $unverified = PayrollUserFactory::new()->create(['email' => 'unverified@levictas.test', 'email_verified_at' => null]);
        $unverified->assignRole('customer');

        $visitors = [
            'guest'      => null,
            'customer'   => $this->customer(),
            'unverified' => $unverified,
            'owner'      => $this->user('owner'),
            'manager'    => $this->user('manager.a'),
            'therapist'  => $this->user('therapist.a1'),
        ];

        $titles = ['legal.terms' => 'Terms and Conditions', 'legal.privacy' => 'Privacy Policy', 'legal.refund' => 'Refund Policy'];

        foreach ($visitors as $who => $user) {
            $user ? $this->actingAs($user) : auth()->logout();

            foreach ($titles as $route => $title) {
                $this->get(route($route))
                    ->assertOk()
                    ->assertSee("<title>{$title} | Levictas</title>", false)
                    ->assertSee('Last updated')
                    ->assertSee('id="topNav"', false)
                    ->assertSee('<footer', false);
            }
        }
    }

    public function test_footer_links_to_the_three_legal_pages_everywhere(): void
    {
        foreach (['/', route('legal.terms'), route('legal.refund')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $footer = substr($html, strpos($html, '<footer'));

            $this->assertStringContainsString('href="' . route('legal.terms') . '">Terms and Conditions</a>', $footer, $url);
            $this->assertStringContainsString('href="' . route('legal.privacy') . '">Privacy Policy</a>', $footer, $url);
            $this->assertStringContainsString('href="' . route('legal.refund') . '">Refund Policy</a>', $footer, $url);
            $this->assertStringNotContainsString('href="#">Terms</a>', $footer, $url);
            $this->assertStringNotContainsString('href="#">Privacy</a>', $footer, $url);
        }
    }

    public function test_pages_show_a_table_of_contents_with_working_anchors(): void
    {
        $html = $this->get(route('legal.privacy'))->assertOk()->getContent();

        foreach (PrivacyPolicy::sections() as $section) {
            $this->assertStringContainsString('href="#' . $section['id'] . '"', $html);
            $this->assertStringContainsString('id="' . $section['id'] . '"', $html);
        }
        $this->assertStringContainsString('Part A — Data Levictas is responsible for', $html);
        $this->assertStringContainsString('Part B — Data we handle for partner spas', $html);
    }

    // ── One source (D8) ──────────────────────────────────────────────────────

    public function test_refund_policy_reuses_the_terms_booking_rules_word_for_word(): void
    {
        $refund = $this->get(route('legal.refund'))->assertOk();

        foreach (RefundPolicy::TERMS_SECTIONS as $id) {
            $refund->assertSee($this->termsText($id), false);
        }
        $refund->assertSee('Subscription payments are not refundable');
    }

    public function test_customer_registration_window_shows_customer_terms_and_the_full_links(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee($this->termsText('cancellation'), false)
            ->assertSee($this->termsText('reviews'), false)
            ->assertDontSee('For partner spas')
            ->assertSee('Read the full Terms and Conditions')
            ->assertSee('and have read the')
            ->assertSee('href="' . route('legal.privacy') . '"', false);
    }

    public function test_business_registration_window_shows_partner_terms(): void
    {
        $this->get(route('register.business'))->assertOk()
            ->assertSee('For partner spas')
            ->assertSee('Levictas charges no fee on bookings.')
            ->assertDontSee('platform transaction fee')
            ->assertDontSee($this->termsText('no-show'), false)
            ->assertSee('Read the full Terms and Conditions');
    }

    public function test_booking_window_shows_the_booking_terms_from_the_same_source(): void
    {
        $this->actingAs($this->customer())->get('/')->assertOk()
            ->assertSee($this->termsText('payments', 1), false)
            ->assertSee($this->termsText('cancellation'), false)
            ->assertSee($this->termsText('no-show'), false)
            ->assertDontSee('restricted future bookings')
            ->assertDontSee('refunded by the spa directly');
    }

    // ── Recording acceptance (Q10-A) ─────────────────────────────────────────

    public function test_registration_records_the_terms_version_on_the_account(): void
    {
        Notification::fake();

        $this->post('/register', [
            'first_name' => 'Tess', 'last_name' => 'Terms', 'email' => 'tess@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'terms' => '1',
        ])->assertRedirect();

        $pending = PendingRegistration::where('email', 'tess@example.com')->firstOrFail();
        $this->assertSame(Terms::VERSION, $pending->terms_version);
        $this->assertNotNull($pending->terms_accepted_at);

        $user = app(PendingRegistrationService::class)->complete($pending);

        $this->assertSame(Terms::VERSION, $user->fresh()->terms_version);
        $this->assertEquals($pending->terms_accepted_at, $user->fresh()->terms_accepted_at);
    }

    private function checkoutWorld(): array
    {
        $branch = Branch::where('name', 'Test Branch A')->firstOrFail();
        $treatment = Treatment::withoutGlobalScopes()->where('branch_id', $branch->id)->firstOrFail();
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            OperatingHours::updateOrCreate(['branch_id' => $branch->id, 'day_of_week' => $day], ['opening_time' => '09:00:00', 'closing_time' => '21:00:00', 'is_closed' => false]);
        }
        $customer = PayrollUserFactory::new()->create(['first_name' => 'Cara', 'last_name' => 'Customer', 'email' => 'cara@levictas.test']);
        $customer->assignRole('customer');

        $payload = [
            'spa_id' => $branch->spa_id, 'branch_id' => $branch->id,
            'customer_phone' => '09171234567', 'treatment' => 'treatment_' . $treatment->id,
            'service_type' => 'in_branch', 'appointment_date' => '2026-10-31', 'start_time' => '10:00',
        ];

        return [$customer, $payload];
    }

    public function test_web_checkout_requires_and_records_the_terms(): void
    {
        [$customer, $payload] = $this->checkoutWorld();
        Http::fake(['api.paymongo.com/*' => Http::response(['data' => ['id' => 'cs_test_1', 'attributes' => ['checkout_url' => 'https://pay.example/cs']]], 200)]);

        $this->actingAs($customer)->postJson(route('bookings.online.checkout'), $payload)
            ->assertStatus(422)->assertJsonValidationErrors('terms_agreed');
        $this->assertSame(0, OnlineReservationPayment::count());

        $this->actingAs($customer)->postJson(route('bookings.online.checkout'), $payload + ['terms_agreed' => '1'])
            ->assertOk();

        $reservation = OnlineReservationPayment::latest('id')->firstOrFail();
        $this->assertSame(Terms::VERSION, $reservation->terms_version);
        $this->assertNotNull($reservation->terms_accepted_at);
    }

    public function test_app_checkout_still_works_without_the_field_and_records_it_when_sent(): void
    {
        [$customer, $payload] = $this->checkoutWorld();
        Http::fake(['api.paymongo.com/*' => fn () => Http::response(['data' => ['id' => 'cs_' . uniqid(), 'attributes' => ['checkout_url' => 'https://pay.example/cs']]], 200)]);
        Sanctum::actingAs($customer);

        $this->postJson('/api/bookings/online/checkout', $payload)->assertOk();
        $this->assertNull(OnlineReservationPayment::latest('id')->first()->terms_version);

        $this->postJson('/api/bookings/online/checkout', ['start_time' => '12:00'] + $payload + ['terms_agreed' => true])->assertOk();
        $this->assertSame(Terms::VERSION, OnlineReservationPayment::latest('id')->first()->terms_version);

        $this->postJson('/api/bookings/online/checkout', ['start_time' => '14:00'] + $payload + ['terms_agreed' => false])
            ->assertStatus(422)->assertJsonValidationErrors('terms_agreed');
    }

    // ── Job applications (Q5-A) ──────────────────────────────────────────────

    public function test_application_form_shows_the_notice_and_needs_consent(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('name="privacy_consent"', false)
            ->assertSee(route('legal.privacy') . '#job-applicants', false);

        $branch = Branch::where('name', 'Test Branch A')->firstOrFail();
        // "We're Hiring" comes from an open job opening since staff scheduling & hiring Phase 5.
        $posting = JobPosting::create([
            'spa_id' => $branch->spa_id, 'branch_id' => $branch->id, 'created_by' => User::where('email', 'owner@levictas.test')->value('id'),
            'title' => 'Massage Therapist', 'role' => 'therapist', 'status' => 'open',
        ]);

        $form = [
            'full_name' => 'Ana Applicant', 'email' => 'ana@example.com', 'phone' => '09171234567',
            'address' => 'Imus, Cavite', 'job_posting_id' => $posting->id, 'schedule_fit' => '1',
            'resume' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf'),
        ];

        $this->postJson('/apply/' . $branch->id, $form)
            ->assertStatus(422)->assertJsonPath('errors.privacy_consent.0', 'Please agree to the privacy notice to send your application.');
        $this->assertSame(0, Applicant::count());

        $form['resume'] = UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf');
        $this->postJson('/apply/' . $branch->id, $form + ['privacy_consent' => '1'])->assertOk();

        $applicant = Applicant::firstOrFail();
        $this->assertSame(PrivacyPolicy::VERSION, $applicant->privacy_consent_version);
        $this->assertNotNull($applicant->privacy_consent_at);
    }

    // ── Shared privacy contact (Q2-A) ────────────────────────────────────────

    public function test_privacy_policy_and_id_consent_notice_share_one_contact(): void
    {
        Config::set('levictas.contact.email', 'privacy@levictas.example');

        $this->get(route('legal.privacy'))->assertSee('mailto:privacy@levictas.example', false);
        $this->assertSame('privacy@levictas.example', ConsentNotice::toArray()['privacy_contact']);
        $this->assertStringContainsString('privacy@levictas.example', collect(ConsentNotice::sections())->last()['body']);
    }

    public function test_placeholders_are_shown_as_text_and_reported(): void
    {
        // config/levictas.php now holds the real details, so none are reported.
        $this->assertSame([], Contact::placeholders());
        $this->assertSame(config('levictas.contact.email'), ConsentNotice::privacyContact());

        // Put the original placeholders back to check how they are handled.
        Config::set('levictas.operator.team', '[TEAM MEMBER NAMES]');
        Config::set('levictas.operator.school', '[SCHOOL NAME]');
        Config::set('levictas.contact.email', '[TEAM EMAIL]');
        Config::set('levictas.contact.address', '[SCHOOL ADDRESS]');

        $this->assertSame([
            'levictas.operator.team', 'levictas.operator.school', 'levictas.contact.email', 'levictas.contact.address',
        ], Contact::placeholders());

        $this->get(route('legal.terms'))->assertOk()
            ->assertSee('[TEAM EMAIL]')
            ->assertDontSee('mailto:[TEAM EMAIL]', false);

        // A placeholder never leaks into the staff ID notice as a contact.
        $this->assertNull(ConsentNotice::privacyContact());
    }
}
