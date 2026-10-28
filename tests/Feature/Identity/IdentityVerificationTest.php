<?php

namespace Tests\Feature\Identity;

use App\Models\Applicant;
use App\Models\Booking;
use App\Models\IdentityFileView;
use App\Models\IdentityVerification;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\User;
use App\Services\Identity\IdentityVerificationService;
use Database\Factories\BranchFactory;
use Database\Factories\PayrollUserFactory;
use Database\Factories\SpaFactory;
use Database\Factories\StaffFactory;
use Database\Seeders\IdentityVerificationTestSeeder;
use Database\Seeders\PayrollTestAccountsRemoveSeeder;
use Database\Seeders\PayrollTestAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 1 — staff identity verification backend: data model, private storage,
 * authorization, file purging, storage:privatize and the public-file allowlist.
 */
class IdentityVerificationTest extends TestCase
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
        $this->seed(RolePermissionSeeder::class);

        $owner = PayrollUserFactory::new()->create(['is_owner' => true]);
        $owner->assignRole('owner');
        $this->spa = SpaFactory::new()->create([
            'owner_id' => $owner->id, 'verification_status' => 'verified',
            'trial_used' => true, 'trial_started_at' => now()->subDay(), 'trial_ends_at' => now()->addYear(),
        ]);
        $branch = BranchFactory::new()->create(['spa_id' => $this->spa->id, 'is_main' => true, 'verification_status' => 'verified']);
        $owner->forceFill(['spa_id' => $this->spa->id, 'branch_id' => $branch->id])->save();

        $this->therapist = $this->staffUser('therapist', $branch->id);

        $this->admin = PayrollUserFactory::new()->create();
        $this->admin->assignRole('admin');

        $this->service = app(IdentityVerificationService::class);
    }

    private function staffUser(string $role, int $branchId, bool $performs = false): User
    {
        $user = PayrollUserFactory::new()->create(['spa_id' => $this->spa->id, 'branch_id' => $branchId]);
        $user->assignRole($role);
        StaffFactory::new()->create([
            'user_id' => $user->id, 'spa_id' => $this->spa->id, 'branch_id' => $branchId,
            'performs_treatments' => $performs,
        ]);

        return $user->fresh();
    }

    private function image(string $name = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 800, 800);
    }

    private function submitted(?User $user = null): IdentityVerification
    {
        $user ??= $this->therapist;
        $this->service->updateProfilePhoto($user, $this->image());

        return $this->service->submit($user->fresh(), 'philid', $this->image('id.jpg'), $this->image('selfie.jpg'));
    }

    // ── Lifecycle ────────────────────────────────────────────────────────────

    public function test_submit_then_approve_verifies_and_deletes_the_id_and_selfie(): void
    {
        $row = $this->submitted();
        $idPath = $row->id_image_path;

        Storage::disk('local')->assertExists([$idPath, $row->selfie_path]);
        $this->assertSame(IdentityVerificationService::STATE_PENDING, $this->service->stateFor($this->therapist->fresh()));

        $this->service->approve($row, $this->admin);

        $row->refresh();
        $this->assertSame('approved', $row->status);
        $this->assertNull($row->id_image_path);
        $this->assertNotNull($row->files_purged_at);
        Storage::disk('local')->assertMissing($idPath);
        $this->assertTrue($this->therapist->fresh()->isIdentityVerified());
        $this->assertSame(1, User::treatmentProviders()->identityVerified()->count());
    }

    public function test_reject_requires_a_reason_deletes_files_and_allows_resubmitting(): void
    {
        $row = $this->submitted();

        $this->expectValidation(fn () => $this->service->reject($row, $this->admin, 'no'));

        $this->service->reject($row, $this->admin, 'The ID is blurry.');
        $this->assertSame('rejected', $row->refresh()->status);
        $this->assertFalse($row->hasFiles());

        $again = $this->service->submit($this->therapist->fresh(), 'passport', $this->image(), $this->image());
        $this->assertTrue($again->isPending());
    }

    public function test_only_one_pending_request_at_a_time(): void
    {
        $this->submitted();

        $this->expectValidation(fn () => $this->service->submit($this->therapist->fresh(), 'philid', $this->image(), $this->image()));
        $this->assertSame(1, IdentityVerification::pending()->count());
    }

    public function test_a_photo_is_required_before_submitting(): void
    {
        $this->expectValidation(fn () => $this->service->submit($this->therapist, 'philid', $this->image(), $this->image()));
    }

    public function test_new_photo_after_approval_clears_verified_status(): void
    {
        $this->service->approve($this->submitted(), $this->admin);

        $result = $this->service->updateProfilePhoto($this->therapist->fresh(), $this->image('new.jpg'));

        $this->assertTrue($result['was_verified']);
        $this->assertFalse($this->therapist->fresh()->isIdentityVerified());
        $this->assertSame('superseded', IdentityVerification::first()->status);
        $this->assertSame('photo_changed', IdentityVerification::first()->superseded_reason);
        $this->assertSame(IdentityVerificationService::STATE_NEEDS_REVERIFICATION, $this->service->stateFor($this->therapist->fresh()));
    }

    public function test_new_photo_while_pending_closes_the_request(): void
    {
        $row = $this->submitted();
        $idPath = $row->id_image_path;

        $result = $this->service->updateProfilePhoto($this->therapist->fresh(), $this->image('new.jpg'));

        $this->assertTrue($result['pending_superseded']);
        $this->assertSame('superseded', $row->refresh()->status);
        Storage::disk('local')->assertMissing($idPath);
    }

    public function test_name_change_clears_verified_status_from_any_route(): void
    {
        $this->service->approve($this->submitted(), $this->admin);

        $this->therapist->fresh()->update(['last_name' => 'Renamed']);

        $this->assertFalse($this->therapist->fresh()->isIdentityVerified());
        $this->assertSame('name_changed', IdentityVerification::first()->superseded_reason);
    }

    public function test_approval_is_refused_and_recorded_when_the_photo_changed_after_submitting(): void
    {
        $row = $this->submitted();
        // Simulate a change the service did not see (e.g. a direct DB edit).
        DB::table('users')->where('id', $this->therapist->id)->update(['profile_photo_path' => 'profile-photos/other.jpg']);

        $this->expectValidation(fn () => $this->service->approve($row, $this->admin));

        $this->assertSame('superseded', $row->refresh()->status);   // committed despite the exception
        $this->assertFalse($this->therapist->fresh()->isIdentityVerified());
    }

    public function test_withdrawing_consent_deletes_pending_files_and_ends_an_approval(): void
    {
        $row = $this->submitted();
        $path = $row->selfie_path;

        $this->service->withdraw($this->therapist->fresh());
        $this->assertSame('withdrawn', $row->refresh()->status);
        $this->assertNotNull($row->consent_withdrawn_at);
        Storage::disk('local')->assertMissing($path);

        $this->service->approve($this->service->submit($this->therapist->fresh(), 'philid', $this->image(), $this->image()), $this->admin);
        $this->service->withdraw($this->therapist->fresh());
        $this->assertFalse($this->therapist->fresh()->isIdentityVerified());
    }

    public function test_photo_cannot_be_removed_while_verified_or_pending(): void
    {
        $this->submitted();

        $this->expectValidation(fn () => $this->service->removeProfilePhoto($this->therapist->fresh()));
        $this->assertTrue($this->therapist->fresh()->hasProfilePhoto());
    }

    public function test_uploads_are_reencoded_without_exif(): void
    {
        // A JPEG with an EXIF (APP1) block carrying a fake GPS marker.
        $jpeg = UploadedFile::fake()->image('x.jpg', 800, 800)->get();
        $exif = "\xFF\xE1".pack('n', 2 + 6 + 12)."Exif\0\0".'GPS-SECRET!!';
        $withExif = substr($jpeg, 0, 2).$exif.substr($jpeg, 2);
        $file = UploadedFile::fake()->createWithContent('exif.jpg', $withExif);

        $this->service->updateProfilePhoto($this->therapist, $file);

        $stored = Storage::disk('local')->get($this->therapist->fresh()->profile_photo_path);
        $this->assertStringNotContainsString('GPS-SECRET', $stored);
        $this->assertStringNotContainsString('Exif', $stored);
    }

    // ── Pool and gates ───────────────────────────────────────────────────────

    public function test_pool_is_therapists_plus_staff_marked_performs_treatments(): void
    {
        $branchId = $this->therapist->branch_id;
        $flagged = $this->staffUser('receptionist', $branchId, performs: true);
        $plain = $this->staffUser('receptionist', $branchId);

        $this->assertTrue($flagged->isTreatmentProvider());
        $this->assertFalse($plain->isTreatmentProvider());
        $this->assertEqualsCanonicalizing(
            [$this->therapist->id, $flagged->id],
            User::treatmentProviders()->pluck('id')->all()
        );
        $this->assertTrue($flagged->can('submit-identity-verification'));
        $this->assertFalse($plain->can('submit-identity-verification'));
        $this->expectValidation(fn () => $this->service->submit($plain, 'philid', $this->image(), $this->image()));
    }

    public function test_profile_photo_visibility(): void
    {
        $this->service->updateProfilePhoto($this->therapist, $this->image());
        $url = $this->therapist->fresh()->profilePhotoUrl();

        $sameSpa = $this->staffUser('receptionist', $this->therapist->branch_id);
        $otherOwner = PayrollUserFactory::new()->create();
        $otherSpa = SpaFactory::new()->create(['owner_id' => $otherOwner->id]);
        $outsider = PayrollUserFactory::new()->create(['spa_id' => $otherSpa->id]);
        $outsider->assignRole('manager');
        $customer = PayrollUserFactory::new()->create();
        $customer->assignRole('customer');

        $this->actingAs($this->therapist)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($sameSpa)->get($url)->assertOk();
        $this->actingAs($this->admin)->get($url)->assertOk();
        $this->actingAs($outsider)->get($url)->assertNotFound();
        $this->actingAs($customer)->get($url)->assertNotFound();

        Booking::create([
            'spa_id' => $this->spa->id, 'branch_id' => $this->therapist->branch_id, 'customer_user_id' => $customer->id,
            'therapist_id' => $this->therapist->id, 'status' => 'reserved', 'service_type' => 'in_home',
            'treatment' => 'treatment_1', 'booking_source' => 'online', 'customer_name' => 'C',
            'appointment_date' => today()->addDay()->toDateString(), 'start_time' => '10:00:00', 'end_time' => '11:00:00',
        ]);
        $this->actingAs($customer)->get($url)->assertOk();
    }

    public function test_admin_file_route_needs_the_permission_logs_views_and_404s_after_decision(): void
    {
        $row = $this->submitted();
        $url = route('admin.identity-verifications.files.show', [$row, 'id']);

        $this->actingAs($this->therapist)->get($url)->assertForbidden();

        $this->actingAs($this->admin)->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(1, IdentityFileView::count());

        $this->service->approve($row, $this->admin);
        $this->actingAs($this->admin)->get($url)->assertNotFound();
    }

    // ── Routes that changed ──────────────────────────────────────────────────

    public function test_staff_cannot_rename_themselves_through_customer_or_api_routes(): void
    {
        $this->actingAs($this->therapist)
            ->patch('/customer/profile', ['first_name' => 'X', 'last_name' => 'Y'])
            ->assertForbidden();

        $this->actingAs($this->therapist, 'sanctum')
            ->putJson('/api/me', ['first_name' => 'Changed', 'last_name' => $this->therapist->last_name])
            ->assertStatus(422);

        // Resending the unchanged name (what the app does on a normal save) is fine.
        $this->actingAs($this->therapist, 'sanctum')
            ->putJson('/api/me', ['first_name' => $this->therapist->first_name, 'last_name' => $this->therapist->last_name])
            ->assertOk();
    }

    public function test_public_image_routes_only_serve_allowlisted_folders(): void
    {
        $png = UploadedFile::fake()->image('a.png')->get();
        Storage::disk('public')->put('branch_profiles/a.png', $png);
        Storage::disk('public')->put('resumes/cv.png', $png);
        Storage::disk('public')->put('spa-verification-documents/permit.png', $png);

        $this->get('/storage/branch_profiles/a.png')->assertOk();
        $this->get('/api/image/branch_profiles/a.png')->assertOk();

        // The old closures fell back to any path on the public disk.
        $this->get('/storage/branch_profiles/resumes/cv.png')->assertNotFound();
        $this->get('/api/image/resumes/cv.png')->assertNotFound();
        $this->get('/api/image/spa-verification-documents/permit.png')->assertNotFound();
        $this->get('/api/image/branch_profiles/../resumes/cv.png')->assertNotFound();
    }

    // ── Commands ─────────────────────────────────────────────────────────────

    public function test_privatize_moves_resumes_and_spa_documents_keeping_paths(): void
    {
        Storage::disk('public')->put('resumes/used.pdf', 'cv');
        Storage::disk('public')->put('resumes/unused.pdf', 'old');
        Storage::disk('public')->put('spa-verification-documents/permit.pdf', 'doc');
        Storage::disk('public')->put('branch_profiles/keep.jpg', 'img');
        DB::table('applicants')->insert($this->applicantRow('resumes/used.pdf'));

        $this->artisan('storage:privatize --dry-run')->assertSuccessful();
        Storage::disk('public')->assertExists('resumes/used.pdf');

        $this->artisan('storage:privatize')->assertSuccessful();

        Storage::disk('local')->assertExists('resumes/used.pdf');
        Storage::disk('local')->assertExists('orphaned/resumes/unused.pdf');
        Storage::disk('local')->assertExists('orphaned/spa-verification-documents/permit.pdf');
        Storage::disk('public')->assertMissing(['resumes/used.pdf', 'resumes/unused.pdf', 'spa-verification-documents/permit.pdf']);
        Storage::disk('public')->assertExists('branch_profiles/keep.jpg');

        $this->artisan('storage:privatize')->assertSuccessful();   // second run: nothing left, no error
    }

    public function test_purge_deletes_leftover_files_but_not_live_ones(): void
    {
        $row = $this->submitted();
        Storage::disk('local')->put('identity-verifications/999/stray.jpg', 'x');
        Storage::disk('local')->put('profile-photos/999/stray.jpg', 'x');
        $this->travel(2)->hours();

        $this->artisan('identity:purge')->assertSuccessful();

        Storage::disk('local')->assertMissing(['identity-verifications/999/stray.jpg', 'profile-photos/999/stray.jpg']);
        Storage::disk('local')->assertExists([$row->id_image_path, $row->selfie_path, $this->therapist->fresh()->profile_photo_path]);
    }

    public function test_demo_seeder_covers_every_state_and_is_removed_with_the_test_spa(): void
    {
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);

        $states = User::where('email', 'like', 'idv.%')->get()
            ->mapWithKeys(fn (User $u) => [$u->email => $this->service->stateFor($u)]);

        $this->assertSame('approved', $states['idv.vera@levictas.test']);
        $this->assertSame('not_started', $states['idv.paolo@levictas.test']);
        $this->assertSame('pending', $states['idv.pia@levictas.test']);
        $this->assertSame('rejected', $states['idv.rey@levictas.test']);
        $this->assertSame('pending', $states['idv.carlo@levictas.test']);
        $this->assertSame('withdrawn', $states['idv.wendy@levictas.test']);
        $this->assertSame('approved', $states['idv.rhea@levictas.test']);
        $this->assertFalse(User::where('email', 'idv.nico@levictas.test')->first()->hasProfilePhoto());
        $this->assertTrue(Staff::where('user_id', User::where('email', 'idv.rhea@levictas.test')->value('id'))->value('performs_treatments'));

        $this->seed(PayrollTestAccountsRemoveSeeder::class);
        $this->assertSame(0, User::where('email', 'like', 'idv.%')->count());
        $this->assertSame(0, IdentityVerification::count());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function expectValidation(callable $call): void
    {
        try {
            $call();
        } catch (ValidationException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Expected a ValidationException.');
    }

    private function applicantRow(string $resume): array
    {
        $columns = DB::getSchemaBuilder()->getColumnListing('applicants');
        $row = ['resume_path' => $resume, 'created_at' => now(), 'updated_at' => now()];

        foreach (['spa_id' => $this->spa->id, 'branch_id' => $this->therapist->branch_id, 'full_name' => 'Test Applicant',
            'email' => 'applicant@levictas.test', 'phone' => '09170000000', 'address' => 'Cavite',
            'position_applied' => 'therapist', 'status' => 'pending', 'source' => 'website'] as $column => $value) {
            if (in_array($column, $columns, true)) {
                $row[$column] = $value;
            }
        }

        return $row;
    }
}
