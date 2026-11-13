<?php

namespace Tests\Feature\Help;

use App\Models\User;
use App\Services\Navigation\Sidebar;
use App\Support\Guides\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Tests\TestCase;

/**
 * Help guides Phase 2: a guide for every sidebar page, screenshots tied to
 * their task, the customer "How to book" FAQ entry, and the Dashboard
 * clock buttons going through the Attendance page's confirmation.
 */
class HelpGuidesPhase2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 09:00:00');
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    // ── Content ─────────────────────────────────────────────────────────────

    public function test_every_sidebar_page_has_a_guide(): void
    {
        preg_match_all("/->link\\('([a-z-]+)'/", file_get_contents(app_path('Services/Navigation/Sidebar.php')), $m);
        $missing = array_values(array_diff(array_unique($m[1]), array_keys(Guides::all())));

        $this->assertSame([], $missing, 'Sidebar pages without a guide: '.implode(', ', $missing));
    }

    public function test_owner_sees_a_guide_for_every_page_in_their_sidebar(): void
    {
        $owner = $this->account('owner@levictas.test');
        $this->actingAs($owner);
        $keys = array_column(Sidebar::links(Sidebar::forCurrentRequest($owner)), 'key');

        $html = $this->get(route('help.index'))->assertOk()->getContent();
        foreach ($keys as $key) {
            $this->assertStringContainsString('<details id="'.$key.'"', $html, "No guide shown for $key");
        }

        // The owner can view Payroll Setup but not change it: the guide still shows, as view-only.
        if (in_array('payroll-setup', $keys, true) && ! $owner->hasBranchPermission('manage payroll setup')) {
            $this->assertStringContainsString(e(Guides::VIEW_ONLY_TIP), $html);
        }
    }

    // ── Screenshots (D5) ────────────────────────────────────────────────────

    public function test_every_screenshot_exists_is_small_and_points_at_a_real_task(): void
    {
        $referenced = [];
        foreach (Guides::all() as $key => $guide) {
            foreach ($guide['images'] ?? [] as $img) {
                $path = public_path($img['src']);
                $referenced[] = realpath($path);
                $this->assertFileExists($path, "$key: {$img['src']}");
                $this->assertLessThan(200 * 1024, filesize($path), "$key: {$img['src']} is over 200 kB");
                $this->assertNotEmpty($img['alt'] ?? '', "$key: alt text");
                $this->assertArrayHasKey($img['task'] ?? 0, $guide['tasks'], "$key: image points at a missing task");
            }
        }

        // No stray files that no guide uses.
        foreach (glob(public_path('images/guides/*.png')) as $file) {
            $this->assertContains(realpath($file), $referenced, basename($file).' is not used by any guide');
        }
    }

    public function test_a_screenshot_shows_only_with_its_task(): void
    {
        // The Appointments image belongs to "Check out", which needs edit appointments.
        $this->actingAs($this->account('reception.a@levictas.test'))->get(route('help.index'))
            ->assertSee('images/guides/appointments.png', false);

        $this->actingAs($this->account('therapist.a1@levictas.test'))->get(route('help.index'))
            ->assertOk()
            ->assertDontSee('images/guides/appointments.png', false)
            ->assertSee('images/guides/schedule.png', false);
    }

    public function test_a_missing_screenshot_hides_its_slot(): void
    {
        $file = public_path('images/guides/schedule.png');
        $moved = $file.'.moved';
        rename($file, $moved);

        try {
            $this->actingAs($this->account('reception.a@levictas.test'))->get(route('help.index'))
                ->assertOk()
                ->assertSee('id="schedule"', false)
                ->assertDontSee('images/guides/schedule.png', false);
        } finally {
            rename($moved, $file);
        }
    }

    // ── Customer FAQ (D7) ───────────────────────────────────────────────────

    public function test_faq_has_how_to_book_and_keeps_its_terms_pulls(): void
    {
        $payments = \App\Support\Legal\Terms::sections(['payments'])[0]['blocks'][1];
        $cancel = \App\Support\Legal\Terms::sections(['cancellation'])[0]['blocks'][0];

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('id="how-to-book"', false)
            ->assertSee('How do I book an appointment?')
            ->assertSee('Click Reserve an appointment.')
            ->assertSee('Reserve An Appointment')
            ->assertSee($payments, false)
            ->assertSee($cancel, false);
    }

    // ── Dashboard clock buttons ─────────────────────────────────────────────

    public function test_dashboard_clock_in_opens_the_attendance_confirmation(): void
    {
        $therapist = $this->account('therapist.a1@levictas.test');

        $this->actingAs($therapist)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('attendance.index', ['clock' => 'in']).'"', false)
            ->assertDontSee('action="'.route('attendance.clock-in').'"', false);

        $this->actingAs($therapist)->get(route('attendance.index', ['clock' => 'in']))
            ->assertOk()
            ->assertSee("get('clock')", false)
            ->assertSee('onclick="openClockInModal()"', false);
    }
}
