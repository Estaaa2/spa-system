<?php

namespace Tests\Feature\Identity;

use App\Mail\IdentityVerificationResult;
use App\Models\IdentityVerification;
use App\Models\Spa;
use App\Models\User;
use App\Services\Identity\IdentityVerificationService;
use Database\Factories\BranchFactory;
use Database\Factories\PayrollUserFactory;
use Database\Factories\SpaFactory;
use Database\Factories\StaffFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * OCR & verification Phase 3:
 * G1 — a request nobody reviews is closed after config('identity.pending_max_days');
 * G2 — deleting an account closes its pending request and deletes the files.
 * RA 10173 Sec. 11(e): keep personal data only as long as necessary.
 */
class RetentionTest extends TestCase
{
    use RefreshDatabase;

    private Spa $spa;
    private User $therapist;
    private User $admin;
    private IdentityVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);

        $owner = PayrollUserFactory::new()->create(['is_owner' => true]);
        $owner->assignRole('owner');
        $this->spa = SpaFactory::new()->create([
            'owner_id' => $owner->id, 'verification_status' => 'verified',
            'trial_used' => true, 'trial_started_at' => now()->subDay(), 'trial_ends_at' => now()->addYear(),
        ]);
        $branch = BranchFactory::new()->create(['spa_id' => $this->spa->id, 'is_main' => true, 'verification_status' => 'verified']);
        $owner->forceFill(['spa_id' => $this->spa->id, 'branch_id' => $branch->id])->save();

        $this->therapist = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $branch->id]);
        $this->therapist->assignRole('therapist');
        StaffFactory::new()->create([
            'user_id' => $this->therapist->id, 'spa_id' => $this->spa->id, 'branch_id' => $branch->id,
        ]);
        $this->therapist = $this->therapist->fresh();

        $this->admin = PayrollUserFactory::new()->create();
        $this->admin->assignRole('admin');

        $this->service = app(IdentityVerificationService::class);
    }

    private function image(string $name = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 800, 800);
    }

    private function submittedDaysAgo(int $days): IdentityVerification
    {
        $this->travel(-$days)->days();
        $this->service->updateProfilePhoto($this->therapist, $this->image());
        $row = $this->service->submit($this->therapist->fresh(), 'philid', $this->image('id.jpg'), $this->image('selfie.jpg'));
        $this->travelBack();

        return $row;
    }

    // ── G1: unreviewed requests expire ───────────────────────────────────────

    public function test_purge_closes_a_request_not_reviewed_within_30_days(): void
    {
        $row = $this->submittedDaysAgo(31);
        $files = [$row->id_image_path, $row->selfie_path];
        Storage::disk('local')->assertExists($files);

        $this->artisan('identity:purge')->assertSuccessful();

        $row->refresh();
        $this->assertSame(IdentityVerification::STATUS_EXPIRED, $row->status);
        $this->assertNull($row->id_image_path);
        $this->assertNull($row->selfie_path);
        $this->assertNotNull($row->files_purged_at);
        Storage::disk('local')->assertMissing($files);

        Mail::assertSent(IdentityVerificationResult::class, function ($mail) {
            return $mail->verification->isExpired()
                && $mail->hasTo($this->therapist->email)
                && $mail->envelope()->subject === 'Levictas: Your identity verification request was closed';
        });
    }

    public function test_a_request_inside_the_limit_is_left_alone(): void
    {
        $row = $this->submittedDaysAgo(29);

        $this->artisan('identity:purge')->assertSuccessful();

        $this->assertSame(IdentityVerification::STATUS_PENDING, $row->fresh()->status);
        Storage::disk('local')->assertExists($row->id_image_path);
        Mail::assertNothingSent();
    }

    public function test_the_limit_follows_config(): void
    {
        config(['identity.pending_max_days' => 14]);
        $row = $this->submittedDaysAgo(15);

        $this->artisan('identity:purge')->assertSuccessful();

        $this->assertSame(IdentityVerification::STATUS_EXPIRED, $row->fresh()->status);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $row = $this->submittedDaysAgo(40);

        $this->artisan('identity:purge', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(IdentityVerification::STATUS_PENDING, $row->fresh()->status);
        Storage::disk('local')->assertExists($row->id_image_path);
        Mail::assertNothingSent();
    }

    public function test_an_expired_request_can_be_submitted_again(): void
    {
        $this->submittedDaysAgo(31);
        $this->artisan('identity:purge')->assertSuccessful();

        $user = $this->therapist->fresh();
        $this->assertSame(IdentityVerificationService::STATE_NOT_STARTED, $this->service->stateFor($user));

        $new = $this->service->submit($user, 'passport', $this->image('id2.jpg'), $this->image('selfie2.jpg'));
        $this->assertTrue($new->isPending());
    }

    public function test_an_expired_request_cannot_be_approved(): void
    {
        $row = $this->submittedDaysAgo(31);
        $this->artisan('identity:purge')->assertSuccessful();

        $this->actingAs($this->admin)
            ->post(route('admin.identity-verifications.approve', $row), [
                'checks' => ['same_person', 'name_matches', 'same_id', 'valid_id'],
            ])
            ->assertSessionHas('error');

        $this->assertNull($this->therapist->fresh()->identity_verified_at);
    }

    public function test_expired_requests_show_in_the_admin_closed_tab(): void
    {
        $this->submittedDaysAgo(31);
        $this->artisan('identity:purge')->assertSuccessful();

        $this->actingAs($this->admin)
            ->get(route('admin.identity-verifications.index', ['tab' => 'closed']))
            ->assertOk()
            ->assertSee('Expired');
    }

    public function test_profile_page_explains_an_expired_request(): void
    {
        $this->submittedDaysAgo(31);
        $this->artisan('identity:purge')->assertSuccessful();

        $this->actingAs($this->therapist->fresh())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee("wasn't reviewed within 30 days", false);
    }

    public function test_api_keeps_the_known_state_and_reports_the_status(): void
    {
        $this->submittedDaysAgo(31);
        $this->artisan('identity:purge')->assertSuccessful();

        $this->actingAs($this->therapist->fresh(), 'sanctum')
            ->getJson('/api/me/identity')
            ->assertOk()
            ->assertJsonPath('identity.state', 'not_started')
            ->assertJsonPath('identity.latest.status', 'expired');
    }

    public function test_consent_notice_states_the_review_limit(): void
    {
        $this->actingAs($this->therapist, 'sanctum')
            ->getJson('/api/me/identity')
            ->assertOk()
            ->assertJsonPath('identity.consent.version', '2026-11-06')
            ->assertSee('if nobody reviews it within 30 days');
    }

    // ── G2: deleted accounts ─────────────────────────────────────────────────

    public function test_deleting_an_account_closes_its_pending_request_and_deletes_the_files(): void
    {
        $row = $this->submittedDaysAgo(1);
        $files = [$row->id_image_path, $row->selfie_path];

        $this->therapist->fresh()->delete();

        $row->refresh();
        $this->assertSame(IdentityVerification::STATUS_SUPERSEDED, $row->status);
        $this->assertSame(IdentityVerification::SUPERSEDED_ACCOUNT_DELETED, $row->superseded_reason);
        $this->assertNull($row->id_image_path);
        Storage::disk('local')->assertMissing($files);
        Mail::assertNothingSent();
    }

    public function test_purge_closes_pending_requests_of_deleted_accounts_as_a_safety_net(): void
    {
        $row = $this->submittedDaysAgo(1);
        $files = [$row->id_image_path, $row->selfie_path];

        // Deleted without model events (e.g. a bulk query), so the hook didn't run.
        User::withoutEvents(fn () => $this->therapist->fresh()->delete());
        $this->assertTrue($row->fresh()->isPending());

        $this->artisan('identity:purge')->assertSuccessful();

        $row->refresh();
        $this->assertSame(IdentityVerification::STATUS_SUPERSEDED, $row->status);
        $this->assertSame(IdentityVerification::SUPERSEDED_ACCOUNT_DELETED, $row->superseded_reason);
        Storage::disk('local')->assertMissing($files);
    }

    public function test_admin_page_explains_a_request_closed_by_account_deletion(): void
    {
        $row = $this->submittedDaysAgo(1);
        $this->therapist->fresh()->delete();

        $this->actingAs($this->admin)
            ->get(route('admin.identity-verifications.show', $row))
            ->assertOk()
            ->assertSee('account was deleted', false);
    }
}
