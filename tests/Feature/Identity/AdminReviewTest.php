<?php

namespace Tests\Feature\Identity;

use App\Mail\IdentityVerificationResult;
use App\Models\IdentityFileView;
use App\Models\IdentityVerification;
use App\Models\User;
use Database\Factories\PayrollUserFactory;
use Database\Seeders\IdentityVerificationTestSeeder;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 3 — platform-admin review page, plus the Phase 2 follow-ups
 * (dashboard reminder Q29, password rules Q30).
 */
class AdminReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private const ALL_CHECKS = ['same_person', 'name_matches', 'same_id', 'valid_id'];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);

        $this->admin = PayrollUserFactory::new()->create(['first_name' => 'Ada', 'last_name' => 'Admin']);
        $this->admin->assignRole('admin');
    }

    private function account(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function pendingOf(string $email): IdentityVerification
    {
        return $this->account($email)->identityVerifications()->pending()->firstOrFail();
    }

    // ── Access ───────────────────────────────────────────────────────────────

    public function test_only_the_platform_admin_can_open_the_pages(): void
    {
        $row = $this->pendingOf('idv.pia@levictas.test');

        foreach (['owner@levictas.test', 'manager.a@levictas.test', 'idv.vera@levictas.test'] as $email) {
            $this->actingAs($this->account($email))->get(route('admin.identity-verifications.index'))->assertForbidden();
            $this->actingAs($this->account($email))->get(route('admin.identity-verifications.show', $row))->assertForbidden();
            $this->actingAs($this->account($email))
                ->post(route('admin.identity-verifications.approve', $row), ['checks' => self::ALL_CHECKS])
                ->assertForbidden();
        }

        $this->assertTrue($row->fresh()->isPending());
    }

    // ── List ─────────────────────────────────────────────────────────────────

    public function test_list_tabs_counts_and_search(): void
    {
        $this->actingAs($this->admin)->get(route('admin.identity-verifications.index'))
            ->assertOk()
            ->assertSee('Pia Pending (test)')
            ->assertSee('Carlo Changed Photo (test)')
            ->assertDontSee('Rey Rejected (test)');

        $this->actingAs($this->admin)->get(route('admin.identity-verifications.index', ['tab' => 'rejected']))
            ->assertOk()->assertSee('Rey Rejected (test)')->assertDontSee('Pia Pending (test)');

        $this->actingAs($this->admin)->get(route('admin.identity-verifications.index', ['tab' => 'closed']))
            ->assertOk()->assertSee('Wendy Withdrew (test)')->assertSee('Replaced');

        $this->actingAs($this->admin)->get(route('admin.identity-verifications.index', ['q' => 'Carlo']))
            ->assertOk()->assertSee('Carlo Changed Photo (test)')->assertDontSee('Pia Pending (test)');
    }

    public function test_admin_nav_shows_the_pending_badge(): void
    {
        $this->actingAs($this->admin)->get(route('admin.identity-verifications.index'))
            ->assertSee('ID Reviews')
            ->assertSee('2<span class="sr-only"> waiting for review</span>', false);
    }

    // ── Review page ──────────────────────────────────────────────────────────

    public function test_review_page_shows_the_three_images_and_logs_file_views_once(): void
    {
        $row = $this->pendingOf('idv.pia@levictas.test');

        $this->actingAs($this->admin)->get(route('admin.identity-verifications.show', $row))
            ->assertOk()
            ->assertSee(route('admin.identity-verifications.files.show', [$row, 'id']))
            ->assertSee(route('admin.identity-verifications.files.show', [$row, 'selfie']))
            ->assertSee(route('admin.identity-verifications.files.show', [$row, 'photo']))
            ->assertSee('Pia Pending (test)')
            ->assertSee('deleted permanently', false);

        $this->actingAs($this->admin)->get(route('admin.identity-verifications.files.show', [$row, 'id']))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.identity-verifications.files.show', [$row, 'id']))->assertOk(); // zoom reload
        $this->actingAs($this->admin)->get(route('admin.identity-verifications.files.show', [$row, 'selfie']))->assertOk();

        $this->assertSame(2, IdentityFileView::where('identity_verification_id', $row->id)->count());
        $this->actingAs($this->admin)->get(route('admin.identity-verifications.show', $row))->assertSee('opened the id');
    }

    // ── Decisions ────────────────────────────────────────────────────────────

    public function test_approve_needs_all_four_checks(): void
    {
        $row = $this->pendingOf('idv.pia@levictas.test');

        $this->actingAs($this->admin)
            ->from(route('admin.identity-verifications.show', $row))
            ->post(route('admin.identity-verifications.approve', $row), ['checks' => ['same_person']])
            ->assertSessionHasErrors('checks');

        $this->assertTrue($row->fresh()->isPending());
    }

    public function test_approve_verifies_deletes_files_and_emails_the_staff_member(): void
    {
        $row = $this->pendingOf('idv.pia@levictas.test');
        $files = [$row->id_image_path, $row->selfie_path];

        $this->actingAs($this->admin)
            ->post(route('admin.identity-verifications.approve', $row), ['checks' => self::ALL_CHECKS])
            ->assertRedirect(route('admin.identity-verifications.index'))
            ->assertSessionHas('success');

        $row->refresh();
        $this->assertSame('approved', $row->status);
        $this->assertSame($this->admin->id, $row->reviewed_by);
        $this->assertTrue($row->user->isIdentityVerified());
        Storage::disk('local')->assertMissing($files);

        Mail::assertSent(IdentityVerificationResult::class, fn ($mail) =>
            $mail->hasTo('idv.pia@levictas.test') && $mail->verification->isApproved());

        // The decided request shows the result; the ID and selfie are gone.
        $this->actingAs($this->admin)->get(route('admin.identity-verifications.show', $row))
            ->assertSee('Approved')->assertSee('Ada Admin')
            ->assertDontSee(route('admin.identity-verifications.files.show', [$row, 'id']));
    }

    public function test_reject_needs_a_reason_and_emails_it(): void
    {
        $row = $this->pendingOf('idv.carlo@levictas.test');

        $this->actingAs($this->admin)
            ->post(route('admin.identity-verifications.reject', $row), ['rejection_reason' => ''])
            ->assertSessionHasErrorsIn('reject', 'rejection_reason');
        $this->assertTrue($row->fresh()->isPending());

        $reason = 'The ID photo is blurry or cut off, so the name or photo on it can\'t be read.';
        $this->actingAs($this->admin)
            ->post(route('admin.identity-verifications.reject', $row), ['rejection_reason' => $reason])
            ->assertRedirect(route('admin.identity-verifications.index'));

        $this->assertSame('rejected', $row->fresh()->status);
        $this->assertFalse($row->fresh()->hasFiles());
        Mail::assertSent(IdentityVerificationResult::class, fn ($mail) =>
            $mail->hasTo('idv.carlo@levictas.test') && $mail->render() !== '' && str_contains($mail->render(), 'blurry'));

        // The staff member sees the reason on their profile.
        $this->actingAs($this->account('idv.carlo@levictas.test'))->get('/profile')->assertSee('blurry');
    }

    public function test_a_decided_request_cannot_be_decided_again(): void
    {
        $row = $this->pendingOf('idv.pia@levictas.test');
        $this->actingAs($this->admin)->post(route('admin.identity-verifications.approve', $row), ['checks' => self::ALL_CHECKS]);

        $this->actingAs($this->admin)
            ->post(route('admin.identity-verifications.reject', $row), ['rejection_reason' => 'Changed my mind'])
            ->assertRedirect(route('admin.identity-verifications.show', $row))
            ->assertSessionHas('error');

        $this->assertSame('approved', $row->fresh()->status);
    }

    public function test_stale_request_is_flagged_and_closed_instead_of_approved(): void
    {
        $row = $this->pendingOf('idv.pia@levictas.test');
        $pia = $row->user;
        $pia->forceFill(['profile_photo_path' => 'profile-photos/'.$pia->id.'/other.jpg'])->saveQuietly();

        $this->actingAs($this->admin)->get(route('admin.identity-verifications.show', $row))
            ->assertSee('changed after they submitted');

        $this->actingAs($this->admin)
            ->post(route('admin.identity-verifications.approve', $row), ['checks' => self::ALL_CHECKS])
            ->assertSessionHas('error');

        $this->assertSame('superseded', $row->fresh()->status);
        $this->assertFalse($pia->fresh()->isIdentityVerified());
    }

    public function test_deleted_account_can_be_rejected_but_not_approved(): void
    {
        $row = $this->pendingOf('idv.pia@levictas.test');
        $row->user->delete();   // soft delete, as Registered Users does

        $this->actingAs($this->admin)->get(route('admin.identity-verifications.show', $row))
            ->assertOk()->assertSee('This account was deleted');

        $this->actingAs($this->admin)
            ->post(route('admin.identity-verifications.approve', $row), ['checks' => self::ALL_CHECKS])
            ->assertSessionHas('error');
        $this->assertTrue($row->fresh()->isPending());

        $this->actingAs($this->admin)
            ->post(route('admin.identity-verifications.reject', $row), ['rejection_reason' => 'Account was deleted.'])
            ->assertRedirect(route('admin.identity-verifications.index'));
        $this->assertFalse($row->fresh()->hasFiles());
        Mail::assertNothingSent();
    }

    // ── Phase 2 follow-ups ───────────────────────────────────────────────────

    public function test_dashboard_reminder_only_when_there_is_something_to_do(): void
    {
        $shows = [
            'idv.paolo@levictas.test' => 'Verify your identity to receive online home-service bookings',
            'idv.rey@levictas.test'   => 'was not approved',
            'idv.rhea@levictas.test'  => null,   // verified
            'idv.pia@levictas.test'   => null,   // waiting for review
            'idv.wendy@levictas.test' => null,   // withdrew: not nagged
            'reception.a@levictas.test' => null, // not in the pool
        ];

        foreach ($shows as $email => $text) {
            $response = $this->actingAs($this->account($email))->get('/dashboard')->assertOk();
            $text
                ? $response->assertSee($text)
                : $response->assertDontSee('Verify your identity')->assertDontSee('identity verification was not approved');
        }
    }

    public function test_profile_password_uses_the_strong_rules_and_must_change(): void
    {
        $user = $this->account('idv.paolo@levictas.test');
        $password = PayrollTestAccountsSeeder::PASSWORD;

        $this->actingAs($user)->from('/profile')->put(route('profile.password'), [
            'current_password' => $password, 'password' => 'weakpass1', 'password_confirmation' => 'weakpass1',
        ])->assertSessionHasErrors('password');

        $this->actingAs($user)->from('/profile')->put(route('profile.password'), [
            'current_password' => $password, 'password' => $password, 'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');
    }
}
