<?php

namespace Tests\Feature\Verification;

use App\Models\Branch;
use App\Models\Spa;
use App\Models\SpaVerificationDocument;
use App\Models\User;
use App\Services\VerificationDocumentScanner;
use Database\Factories\BranchFactory;
use Database\Factories\PayrollUserFactory;
use Database\Factories\SpaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * OCR is a helper only:
 * it never blocks an upload, and an administrator always confirms the
 * expiration date (a date, or "No expiration date" where allowed).
 *
 * The scanner is mocked, so these tests do not need Tesseract/Poppler.
 * All files are generated fakes; no real ID is used.
 */
class DocumentOcrHelperTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Spa $spa;
    private Branch $main;
    private User $admin;

    /** Scan result the mocked scanner returns next. */
    private array $scan;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-06 09:00:00');
        Mail::fake();
        Storage::fake('local');
        $this->withoutVite();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->owner = PayrollUserFactory::new()->create(['is_owner' => true]);
        $this->owner->assignRole('owner');

        $this->spa = SpaFactory::new()->create([
            'owner_id' => $this->owner->id,
            'verification_status' => 'unverified',
        ]);

        $this->main = BranchFactory::new()->create([
            'spa_id' => $this->spa->id,
            'is_main' => true,
            'verification_status' => 'unverified',
        ]);

        $this->owner->forceFill([
            'spa_id' => $this->spa->id,
            'branch_id' => $this->main->id,
        ])->save();

        $this->admin = PayrollUserFactory::new()->create();
        $this->admin->assignRole('admin');

        $this->scan = $this->scanResult('failed');

        $this->app->instance(
            VerificationDocumentScanner::class,
            new class($this) extends VerificationDocumentScanner {
                public function __construct(private DocumentOcrHelperTest $test)
                {
                }

                public function scan(string $absolutePath, ?string $mimeType = null): array
                {
                    return $this->test->nextScan();
                }
            }
        );
    }

    public function nextScan(): array
    {
        return $this->scan;
    }

    private function scanResult(string $status, ?string $date = null, ?string $raw = null): array
    {
        return [
            'expiry_date' => $date,
            'expiry_date_raw' => $raw,
            'expiry_detection_status' => $status,
            'expiry_detection_source' => $status === 'failed' || $status === 'unavailable' ? null : 'ocr_image',
            'expiry_scanned_at' => now(),
        ];
    }

    private function upload(array $files, array $extra = [])
    {
        return $this->actingAs($this->owner)
            ->from(route('setup.documents'))
            ->post(route('owner.spa-profile.documents.upload'), [
                'documents' => $files,
                'intent' => 'submit',
                ...$extra,
            ]);
    }

    private function fakeFile(string $name = 'doc.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 800, 500);
    }

    private function doc(string $type): ?SpaVerificationDocument
    {
        return SpaVerificationDocument::where('spa_id', $this->spa->id)
            ->where('document_type', $type)
            ->first();
    }

    /** All four documents on file, spa waiting for review. */
    private function pendingSpa(): void
    {
        $this->upload([
            'government_id' => $this->fakeFile('id.jpg'),
            'dti_sec' => $this->fakeFile('dti.jpg'),
            'bir_certificate' => $this->fakeFile('bir.jpg'),
            'business_permit' => $this->fakeFile('permit.jpg'),
        ]);

        $this->spa->refresh();
        $this->assertSame('pending', $this->spa->verification_status);
    }

    private function approve(array $expiry, array $noExpiry = [])
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.registered-spas.index'))
            ->put(route('admin.registered-spas.update', $this->spa), [
                'verification_status' => 'verified',
                'document_expiry' => $expiry,
                'document_no_expiry' => $noExpiry,
            ]);
    }

    public static function statuses(): array
    {
        return [
            'detected' => ['detected', '2027-03-01'],
            'needs_review' => ['needs_review', null],
            'not_found' => ['not_found', null],
            'unreadable' => ['unreadable', null],
            'failed' => ['failed', null],
            'unavailable' => ['unavailable', null],
        ];
    }

    // ---------------------------------------------------------------
    // Owner upload: never blocked
    // ---------------------------------------------------------------

    #[DataProvider('statuses')]
    public function test_setup_upload_is_saved_for_every_scan_status(string $status, ?string $date): void
    {
        $this->scan = $this->scanResult($status, $date);

        $response = $this->upload([
            'government_id' => $this->fakeFile('id.jpg'),
            'business_permit' => $this->fakeFile('permit.jpg'),
        ]);

        $response->assertSessionHasNoErrors();

        foreach (['government_id', 'business_permit'] as $type) {
            $doc = $this->doc($type);
            $this->assertNotNull($doc, "$type was not saved for status $status");
            $this->assertSame($status, $doc->expiry_detection_status);
            $this->assertSame($date, $doc->ocr_expiry_date?->format('Y-m-d'));
            $this->assertNull($doc->expiry_date, 'only the admin sets the confirmed date');
            Storage::disk('local')->assertExists($doc->file_path);
        }
    }

    public function test_owner_date_is_stored_as_owner_date_not_the_ocr_date(): void
    {
        $this->scan = $this->scanResult('detected', '2027-01-01');

        $this->upload(
            ['business_permit' => $this->fakeFile()],
            ['owner_expiry' => ['business_permit' => '2027-02-02']]
        )->assertSessionHasNoErrors();

        $doc = $this->doc('business_permit');
        $this->assertSame('2027-02-02', $doc->owner_expiry_date->format('Y-m-d'));
        $this->assertSame('2027-01-01', $doc->ocr_expiry_date->format('Y-m-d'));
    }

    public function test_owner_date_stays_empty_when_owner_gives_none(): void
    {
        $this->scan = $this->scanResult('detected', '2027-01-01');

        $this->upload(['government_id' => $this->fakeFile()])->assertSessionHasNoErrors();

        $this->assertNull($this->doc('government_id')->owner_expiry_date);
    }

    public function test_owner_can_mark_government_id_as_having_no_expiry(): void
    {
        $this->upload(
            ['government_id' => $this->fakeFile()],
            [
                'owner_expiry' => ['government_id' => '2030-01-01'],
                'owner_no_expiry' => ['government_id' => '1'],
            ]
        )->assertSessionHasNoErrors();

        $doc = $this->doc('government_id');
        $this->assertTrue($doc->owner_no_expiry);
        $this->assertNull($doc->owner_expiry_date, 'the tick wins over a typed date');
    }

    public function test_no_expiry_tick_is_ignored_for_business_permit(): void
    {
        $this->upload(
            ['business_permit' => $this->fakeFile()],
            ['owner_no_expiry' => ['business_permit' => '1']]
        )->assertSessionHasNoErrors();

        $this->assertFalse($this->doc('business_permit')->owner_no_expiry);
    }

    public function test_bir_is_not_scanned(): void
    {
        $this->scan = $this->scanResult('detected', '2027-01-01');

        $this->upload(['bir_certificate' => $this->fakeFile()])->assertSessionHasNoErrors();

        $doc = $this->doc('bir_certificate');
        $this->assertSame('not_required', $doc->expiry_detection_status);
        $this->assertNull($doc->ocr_expiry_date);
    }

    public function test_owner_setup_page_shows_a_neutral_note_not_an_error(): void
    {
        $this->scan = $this->scanResult('unreadable');

        $this->upload(['government_id' => $this->fakeFile()]);

        $this->actingAs($this->owner)
            ->get(route('setup.documents'))
            ->assertOk()
            ->assertSee('An administrator will confirm the expiration date during review.')
            ->assertDontSee('is unreadable or its expiration date could not be detected');
    }

    public function test_owner_setup_page_echoes_the_owners_own_answer(): void
    {
        $this->upload(
            ['government_id' => $this->fakeFile(), 'business_permit' => $this->fakeFile()],
            [
                'owner_no_expiry' => ['government_id' => '1'],
                'owner_expiry' => ['business_permit' => '2026-12-31'],
            ]
        );

        $this->actingAs($this->owner)
            ->get(route('setup.documents'))
            ->assertOk()
            ->assertSee('You marked this as having no expiration date.')
            ->assertSee('Expiration date you entered: Dec 31, 2026.');
    }

    public function test_spa_submits_for_review_even_when_ocr_reads_nothing(): void
    {
        $this->scan = $this->scanResult('unavailable');

        $this->pendingSpa();
    }

    // ---------------------------------------------------------------
    // Admin approval: the real check
    // ---------------------------------------------------------------

    private function ids(): array
    {
        return collect(['government_id', 'dti_sec', 'bir_certificate', 'business_permit'])
            ->mapWithKeys(fn ($t) => [$t => $this->doc($t)->id])
            ->all();
    }

    public function test_admin_cannot_approve_with_a_blank_government_id_date(): void
    {
        $this->pendingSpa();
        $id = $this->ids();

        $this->approve([
            $id['dti_sec'] => '2030-01-01',
            $id['business_permit'] => '2026-12-31',
        ])->assertSessionHas('error');

        $this->assertSame('pending', $this->spa->fresh()->verification_status);
    }

    public function test_admin_cannot_approve_with_a_blank_dti_sec_date(): void
    {
        $this->pendingSpa();
        $id = $this->ids();

        $this->approve([
            $id['government_id'] => '2030-01-01',
            $id['business_permit'] => '2026-12-31',
        ])->assertSessionHas('error');

        $this->assertSame('pending', $this->spa->fresh()->verification_status);
    }

    public function test_admin_cannot_approve_without_a_business_permit_date(): void
    {
        $this->pendingSpa();
        $id = $this->ids();

        $this->approve(
            [$id['government_id'] => '2030-01-01', $id['dti_sec'] => '2030-01-01'],
        )->assertSessionHas('error');

        $this->assertSame('pending', $this->spa->fresh()->verification_status);
    }

    public function test_admin_cannot_mark_business_permit_as_no_expiry(): void
    {
        $this->pendingSpa();
        $id = $this->ids();

        $this->approve(
            [$id['government_id'] => '2030-01-01', $id['dti_sec'] => '2030-01-01'],
            [$id['business_permit'] => '1']
        )->assertSessionHas('error');

        $this->assertSame('pending', $this->spa->fresh()->verification_status);
    }

    public function test_admin_cannot_approve_an_expired_date(): void
    {
        $this->pendingSpa();
        $id = $this->ids();

        $this->approve([
            $id['government_id'] => '2026-01-01',
            $id['dti_sec'] => '2030-01-01',
            $id['business_permit'] => '2026-12-31',
        ])->assertSessionHas('error');

        $this->assertSame('pending', $this->spa->fresh()->verification_status);
    }

    public function test_admin_approves_with_dates_and_no_expiry_ticks(): void
    {
        $this->pendingSpa();
        $id = $this->ids();

        $this->approve(
            [$id['business_permit'] => '2026-12-31', $id['dti_sec'] => '2031-05-01'],
            [$id['government_id'] => '1']
        )->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('verified', $this->spa->fresh()->verification_status);

        $gov = $this->doc('government_id');
        $this->assertSame('verified_no_expiry', $gov->expiry_detection_status);
        $this->assertNull($gov->expiry_date);
        $this->assertNotNull($gov->expiry_verified_at);

        $permit = $this->doc('business_permit');
        $this->assertSame('verified', $permit->expiry_detection_status);
        $this->assertSame('2026-12-31', $permit->expiry_date->format('Y-m-d'));

        $this->assertSame('not_required', $this->doc('bir_certificate')->expiry_detection_status);
    }

    public function test_admin_modal_payload_includes_owner_no_expiry(): void
    {
        $this->upload(
            ['government_id' => $this->fakeFile()],
            ['owner_no_expiry' => ['government_id' => '1']]
        );

        $this->actingAs($this->admin)
            ->getJson(route('admin.registered-spas.edit', $this->spa))
            ->assertOk()
            ->assertJsonPath('spa.documents.0.owner_no_expiry', true);
    }

    // ---------------------------------------------------------------
    // Verified spa: replacing a document
    // ---------------------------------------------------------------

    private function verifiedSpa(): void
    {
        $this->pendingSpa();
        $id = $this->ids();

        $this->approve([
            $id['government_id'] => '2026-11-20',
            $id['dti_sec'] => '2031-05-01',
            $id['business_permit'] => '2026-12-31',
        ])->assertSessionHas('success');

        $this->spa->refresh();
        $this->assertSame('verified', $this->spa->verification_status);

        // The cached spa relation on the acting user still says "pending".
        $this->owner = $this->owner->fresh();
    }

    public function test_verified_owner_must_give_a_date_or_tick_when_replacing_government_id(): void
    {
        $this->verifiedSpa();

        $this->actingAs($this->owner)
            ->post(route('owner.spa-profile.documents.upload'), [
                'documents' => ['government_id' => $this->fakeFile('new-id.jpg')],
            ])
            ->assertSessionHasErrors('documents.government_id');

        $this->assertSame('2026-11-20', $this->doc('government_id')->expiry_date->format('Y-m-d'));
    }

    public function test_verified_owner_can_replace_government_id_with_a_no_expiry_id(): void
    {
        $this->verifiedSpa();
        $this->scan = $this->scanResult('failed');

        $this->actingAs($this->owner)
            ->post(route('owner.spa-profile.documents.upload'), [
                'documents' => ['government_id' => $this->fakeFile('philid.jpg')],
                'owner_no_expiry' => ['government_id' => '1'],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $doc = $this->doc('government_id');
        $this->assertSame('philid.jpg', $doc->file_name);
        $this->assertTrue($doc->owner_no_expiry);
        $this->assertNull($doc->expiry_verified_at, 'waits for admin review');
        $this->assertSame(
            '2026-11-20',
            $doc->expiry_date->format('Y-m-d'),
            'the last confirmed date stays in effect until review'
        );
        $this->assertSame('verified', $this->spa->fresh()->verification_status);
    }

    public function test_verified_replace_still_requires_permit_date(): void
    {
        $this->verifiedSpa();
        Carbon::setTestNow('2026-12-10 09:00:00'); // inside the 30-day renewal window

        $this->actingAs($this->owner)
            ->post(route('owner.spa-profile.documents.upload'), [
                'documents' => ['business_permit' => $this->fakeFile('permit-2027.jpg')],
                'owner_no_expiry' => ['business_permit' => '1'],
            ])
            ->assertSessionHasErrors('documents.business_permit');
    }

    public function test_document_review_accepts_no_expiry_for_a_replaced_government_id(): void
    {
        $this->verifiedSpa();

        $this->actingAs($this->owner)
            ->post(route('owner.spa-profile.documents.upload'), [
                'documents' => ['government_id' => $this->fakeFile('philid.jpg')],
                'owner_no_expiry' => ['government_id' => '1'],
            ]);

        $doc = $this->doc('government_id');

        $this->actingAs($this->admin)
            ->put(route('admin.document-reviews.update', $doc), [
                'verification_status' => 'verified',
                'document_no_expiry' => [$doc->id => '1'],
            ])
            ->assertSessionHas('success');

        $doc->refresh();
        $this->assertSame('verified_no_expiry', $doc->expiry_detection_status);
        $this->assertNull($doc->expiry_date);
        $this->assertNotNull($doc->expiry_verified_at);
    }

    public function test_document_review_rejects_a_blank_government_id_date(): void
    {
        $this->verifiedSpa();

        $this->actingAs($this->owner)
            ->post(route('owner.spa-profile.documents.upload'), [
                'documents' => ['government_id' => $this->fakeFile('id2.jpg')],
                'owner_expiry' => ['government_id' => '2031-01-01'],
            ]);

        $doc = $this->doc('government_id');

        $this->actingAs($this->admin)
            ->put(route('admin.document-reviews.update', $doc), [
                'verification_status' => 'verified',
            ])
            ->assertSessionHas('error');

        $this->assertNull($doc->fresh()->expiry_verified_at);
    }

    // ---------------------------------------------------------------
    // Additional branch: first submission
    // ---------------------------------------------------------------

    public function test_new_branch_permit_is_saved_even_when_unreadable(): void
    {
        $this->verifiedSpa();
        $this->spa->forceFill([
            'trial_used' => true,
            'trial_started_at' => now()->subDay(),
            'trial_ends_at' => now()->addDays(20),
        ])->save();

        $branch = BranchFactory::new()->create([
            'spa_id' => $this->spa->id,
            'verification_status' => 'unverified',
        ]);

        $this->scan = $this->scanResult('unreadable');

        $this->actingAs($this->owner)
            ->post(route('owner.spa-profile.branches.documents.upload', $branch), [
                'branch_documents' => [
                    'bir_certificate' => $this->fakeFile('bir-b.jpg'),
                    'business_permit' => $this->fakeFile('permit-b.jpg'),
                ],
            ])
            ->assertSessionHasNoErrors();

        $permit = SpaVerificationDocument::where('branch_id', $branch->id)
            ->where('document_type', 'business_permit')
            ->first();

        $this->assertNotNull($permit);
        $this->assertSame('unreadable', $permit->expiry_detection_status);
        $this->assertSame('pending', $branch->fresh()->verification_status);
    }

    // ---------------------------------------------------------------
    // Scanner itself (no binaries needed)
    // ---------------------------------------------------------------

    public function test_scanner_reports_unavailable_and_logs_once_when_tesseract_is_missing(): void
    {
        config(['document_scanner.tesseract' => '/definitely/not/installed/tesseract']);
        Log::spy();

        $scanner = new VerificationDocumentScanner();
        $file = $this->fakeFile()->getRealPath();

        $this->assertSame('unavailable', $scanner->scan($file, 'image/jpeg')['expiry_detection_status']);
        $this->assertSame('unavailable', $scanner->scan($file, 'image/jpeg')['expiry_detection_status']);

        Log::shouldHaveReceived('warning')->once();
    }

    public static function dateTexts(): array
    {
        return [
            'period after day' => ['and is valid until December 31. 2026 unless revoked', 'detected', '2026-12-31'],
            'EXP abbreviation' => ["EXP\n2029-03-01", 'detected', '2029-03-01'],
            'Exp. Date' => ['Exp. Date: 14 OCT 2031', 'detected', '2031-10-14'],
            'Experience is not a label' => ['Years of Experience 2020/01/01', 'not_found', null],
            'ambiguous numeric' => ["Valid Until\n06/05/2027", 'needs_review', null],
            'issue date only' => ['Date of Issue 2023/01/15', 'not_found', null],
        ];
    }

    #[DataProvider('dateTexts')]
    public function test_scanner_date_reading(string $text, string $status, ?string $date): void
    {
        $method = new \ReflectionMethod(VerificationDocumentScanner::class, 'extractExpiryDate');

        $result = $method->invoke(new VerificationDocumentScanner(), $text);

        $this->assertSame($status, $result['status']);
        $this->assertSame($date, $result['date']);
    }
}
