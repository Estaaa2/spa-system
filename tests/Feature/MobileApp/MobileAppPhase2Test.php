<?php

namespace Tests\Feature\MobileApp;

use App\Models\User;
use App\Support\MobileApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Tests\TestCase;

/**
 * Mobile app Phase 2: the public "Get the app" page, the APK download, and
 * the links to it (landing nav, phone menu, footer, business sidebar).
 */
class MobileAppPhase2Test extends TestCase
{
    use RefreshDatabase;

    private const FILE = 'phase2-test-app.apk';

    private const SHA = '268fed0dc9e1d52faee88b5afb30c25c90891874533f7eb7d5165fb53ac67930';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-14 09:00:00');
        $this->withoutVite();

        config([
            'levictas.mobile_app.file' => self::FILE,
            'levictas.mobile_app.version' => '1.0.0',
            'levictas.mobile_app.released' => '2026-11-14',
            'levictas.mobile_app.min_android' => '7.0',
            'levictas.mobile_app.sha256' => self::SHA,
        ]);
        $this->removeApk();
    }

    protected function tearDown(): void
    {
        $this->removeApk();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function putApk(int $bytes = 2_097_152): void
    {
        @mkdir(dirname(MobileApp::path()), 0775, true);
        file_put_contents(MobileApp::path(), str_repeat('A', $bytes));
    }

    private function removeApk(): void
    {
        @unlink(storage_path('app/mobile/'.self::FILE));
    }

    private function seedAccounts(): void
    {
        $this->seed(PayrollTestAccountsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function account(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    // ── Page ───────────────────────────────────────────────────────────────

    public function test_the_page_shows_the_download_and_details_when_the_apk_is_there(): void
    {
        $this->putApk();

        $this->get('/get-the-app')
            ->assertOk()
            ->assertSee('Get the Levictas app')
            ->assertSee('Version 1.0.0')
            ->assertSee('Released Nov 14, 2026')
            ->assertSee('2.0 MB')
            ->assertSee('Android 7.0 or newer')
            ->assertSee(self::SHA)
            ->assertSee(route('mobile-app.download'), false)
            ->assertSee('Install anyway')
            ->assertSee('Using an iPhone?');
    }

    public function test_the_page_says_not_available_when_the_apk_is_missing(): void
    {
        $this->get('/get-the-app')
            ->assertOk()
            ->assertSee("The download isn't available right now.", false)
            ->assertDontSee('data-testid="app-download"', false);
    }

    public function test_no_version_set_counts_as_not_available(): void
    {
        $this->putApk();
        config(['levictas.mobile_app.version' => '']);

        $this->get('/get-the-app')->assertOk()->assertDontSee('data-testid="app-download"', false);
    }

    public function test_the_page_works_for_customers_and_business_users_too(): void
    {
        $this->seedAccounts();
        $this->putApk();

        $this->actingAs($this->account('owner@levictas.test'))->get('/get-the-app')->assertOk();
        $this->actingAs($this->account('therapist.a1@levictas.test'))->get('/get-the-app')->assertOk();
    }

    public function test_the_page_makes_no_owner_feature_promises(): void
    {
        $this->get('/get-the-app')
            ->assertSee('For customers')
            ->assertSee('For therapists')
            ->assertSee('the app is for your customers and therapists')
            ->assertDontSee('For owners');
    }

    // ── QR ─────────────────────────────────────────────────────────────────

    public function test_the_qr_shows_only_on_the_real_domain(): void
    {
        $this->putApk();

        // Tests run on http://localhost: a QR for levictas.com would be wrong here.
        $this->get('/get-the-app')->assertDontSee('images/get-the-app-qr.svg', false);

        URL::forceRootUrl('https://levictas.com');
        URL::forceScheme('https');
        $this->assertTrue(MobileApp::showQr());
        $this->get('/get-the-app')->assertSee('images/get-the-app-qr.svg', false);
        URL::forceRootUrl(null);
        URL::forceScheme(null);
    }

    public function test_the_qr_file_is_committed(): void
    {
        $svg = file_get_contents(public_path('images/get-the-app-qr.svg'));

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('levictas.com/get-the-app', $svg); // its <title>
    }

    // ── Download ───────────────────────────────────────────────────────────

    public function test_the_download_sends_the_apk_with_the_right_headers(): void
    {
        $this->putApk(1024);

        $response = $this->get('/app/download');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.android.package-archive')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('Levictas-1.0.0.apk', $response->headers->get('Content-Disposition'));
        $this->assertSame(1024, (int) $response->headers->get('Content-Length'));
    }

    public function test_a_missing_apk_sends_people_back_to_the_page_not_a_500(): void
    {
        $this->get('/app/download')->assertRedirect(route('mobile-app'));
    }

    public function test_the_file_setting_cannot_point_outside_the_folder(): void
    {
        config(['levictas.mobile_app.file' => '../../.env']);

        $this->assertSame(storage_path('app/mobile/.env'), MobileApp::path());
        $this->get('/app/download')->assertRedirect(route('mobile-app'));
    }

    // ── Links ──────────────────────────────────────────────────────────────

    public function test_guests_see_the_link_in_the_nav_phone_menu_and_footer(): void
    {
        $html = $this->get('/about')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'href="'.route('mobile-app').'"'));
    }

    public function test_the_landing_page_has_the_link(): void
    {
        $this->seedAccounts(); // the landing page needs the roles

        $this->get('/')->assertOk()->assertSee('href="'.route('mobile-app').'"', false);
    }

    public function test_therapists_see_get_the_mobile_app_in_the_sidebar(): void
    {
        $this->seedAccounts();

        $this->actingAs($this->account('therapist.a1@levictas.test'))->get('/dashboard')
            ->assertSee('data-testid="sidebar-app-link"', false)
            ->assertSee('Get the mobile app');
    }

    public function test_owners_see_the_share_wording_in_the_sidebar(): void
    {
        $this->seedAccounts();

        $this->actingAs($this->account('owner@levictas.test'))->get('/dashboard')
            ->assertSee('data-testid="sidebar-app-link"', false)
            ->assertSee('Get the app for your therapists');
    }

    public function test_other_staff_get_no_sidebar_link(): void
    {
        $this->seedAccounts();

        $this->actingAs($this->account('reception.a@levictas.test'))->get('/dashboard')
            ->assertOk()
            ->assertDontSee('data-testid="sidebar-app-link"', false);
    }

    public function test_routes_exist(): void
    {
        $this->assertSame(url('/get-the-app'), route('mobile-app'));
        $this->assertSame(url('/app/download'), route('mobile-app.download'));
    }
}
