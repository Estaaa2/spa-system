<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\VerificationReviewResult;
use App\Models\Branch;
use App\Models\Spa;
use App\Models\SpaVerificationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BranchVerificationController extends Controller
{
    private const DOCUMENT_LABELS = [
        'government_id' => 'Government ID',
        'dti_sec' => 'DTI / SEC Certificate',
        'bir_certificate' => 'BIR Certificate of Registration',
        'business_permit' => 'Business Permit',
    ];

    private const DOCUMENT_ORDER = [
        'government_id',
        'dti_sec',
        'bir_certificate',
        'business_permit',
    ];

    private const PLANS = [
        'basic',
        'premium',
        'business',
    ];

    /**
     * One branch application, in the same JSON shape the spa review
     * modal already understands (so the modal is reused as-is).
     */
    public function edit(Branch $branch)
    {
        $branch->load([
            'spa.owner',
            'verificationDocuments',
            'verifier',
        ]);

        $spa = $branch->spa;

        abort_unless($spa, 404);

        $ownDocuments = $branch->verificationDocuments;

        // Government ID and DTI/SEC come from the spa. They are shown
        // for reference only and are not changed by this review.
        $sharedDocuments = $spa->verificationDocuments()
            ->whereNull('branch_id')
            ->whereIn('document_type', ['government_id', 'dti_sec'])
            ->get()
            ->reject(fn ($document) =>
                $ownDocuments->contains(
                    'document_type',
                    $document->document_type
                )
            );

        $documents = $sharedDocuments
            ->map(fn ($document) => $this->formatDocument($document, true))
            ->concat(
                $ownDocuments->map(
                    fn ($document) => $this->formatDocument($document, false)
                )
            )
            ->sortBy(fn ($document) => array_search(
                $document['document_type'],
                self::DOCUMENT_ORDER,
                true
            ))
            ->values();

        $plan = strtolower((string) ($spa->business_tier ?: 'basic'));

        if ($plan === 'professional') {
            $plan = 'premium';
        }

        if (! in_array($plan, self::PLANS, true)) {
            $plan = 'basic';
        }

        return response()->json([
            'spa' => [
                'id' => $branch->id,
                'name' => $spa->name . ' — ' . $branch->name .
                    ' (' . $branch->location . ')',
                'business_tier' => $plan,
                'verification_status' => $branch->verification_status,
                'verification_remarks' => $branch->verification_remarks,
                'verified_at' => $branch->verified_at?->format('F d, Y h:i A'),
                'owner_name' => $spa->owner?->name ?? 'N/A',
                'owner_email' => $spa->owner?->email ?? 'N/A',
                'verified_by' => $branch->verifier?->name,
                'documents' => $documents,
            ],
        ]);
    }

    /**
     * Approve or reject one branch application as a whole.
     * Other branches and the spa itself are never touched.
     */
    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'verification_status' => [
                'required',
                'in:verified,rejected',
            ],

            'verification_remarks' => [
                'nullable',
                'string',
                'max:5000',
                'required_if:verification_status,rejected',
            ],

            'document_expiry' => [
                'nullable',
                'array',
            ],

            'document_expiry.*' => [
                'nullable',
                'date_format:Y-m-d',
            ],
        ]);

        if ($branch->verification_status !== 'pending') {
            return back()->with(
                'error',
                'This branch is not waiting for review.'
            );
        }

        if ($validated['verification_status'] === 'rejected') {
            $branch->update([
                'verification_status' => 'rejected',
                'verification_remarks' => trim(
                    (string) $validated['verification_remarks']
                ),
                'verified_at' => null,
                'verified_by' => null,
            ]);

            $this->notifyOwner(
                $branch->spa,
                $branch->name . ' was not approved',
                'The documents submitted for ' . $branch->name .
                ' were reviewed and could not be approved. Please correct them and submit the branch again from your Spa Profile.',
                false,
                $branch->verification_remarks
            );

            return redirect()
                ->route('admin.registered-spas.index')
                ->with(
                    'success',
                    'Branch rejected. The owner must correct and resubmit its documents.'
                );
        }

        if ($branch->spa?->verification_status !== 'verified') {
            return back()->with(
                'error',
                'The spa itself must be verified before one of its branches can be approved.'
            );
        }

        // The owner's Government ID is reused, but only while it is valid.
        $governmentId = $branch->documentFor('government_id');

        if (! $governmentId) {
            return back()->with(
                'error',
                'This branch cannot be approved because the owner has no Government ID on file.'
            );
        }

        if (
            $governmentId->expiry_date &&
            $governmentId->expiry_date->isBefore(today())
        ) {
            return back()->with(
                'error',
                'This branch cannot be approved because the owner\'s Government ID has expired. Ask the owner to replace it first.'
            );
        }

        if (! $branch->documentFor('dti_sec')) {
            return back()->with(
                'error',
                'This branch cannot be approved because there is no DTI / SEC Certificate on file.'
            );
        }

        // Only this branch's own documents are reviewed and updated.
        $documents = $branch->verificationDocuments()->get();

        $missing = array_diff(
            Branch::BRANCH_DOCUMENT_TYPES,
            $documents->pluck('document_type')->all()
        );

        if ($missing !== []) {
            $missingLabels = collect($missing)
                ->map(fn ($type) => self::DOCUMENT_LABELS[$type] ?? $type)
                ->implode(', ');

            return back()->with(
                'error',
                'This branch cannot be approved because these documents are missing: ' .
                $missingLabels . '.'
            );
        }

        $submittedExpiry = $validated['document_expiry'] ?? [];

        $businessPermit = $documents->firstWhere(
            'document_type',
            'business_permit'
        );

        if (! filled($submittedExpiry[$businessPermit->id] ?? null)) {
            return back()->with(
                'error',
                'Confirm the Business Permit expiration date before approving this branch.'
            );
        }

        foreach ($documents as $document) {
            if ($document->document_type === 'bir_certificate') {
                continue;
            }

            $expiry = $submittedExpiry[$document->id] ?? null;

            if (! filled($expiry)) {
                continue;
            }

            if (
                Carbon::createFromFormat('Y-m-d', $expiry)
                    ->startOfDay()
                    ->isBefore(today())
            ) {
                $label = self::DOCUMENT_LABELS[$document->document_type]
                    ?? $document->document_type;

                return back()->with(
                    'error',
                    "{$label} cannot be approved because its expiration date has already passed."
                );
            }
        }

        DB::transaction(function () use (
            $branch,
            $documents,
            $submittedExpiry
        ) {
            foreach ($documents as $document) {
                if ($document->document_type === 'bir_certificate') {
                    $document->update([
                        'expiry_date' => null,
                        'expiry_date_raw' => null,
                        'expiry_detection_status' => 'not_required',
                        'expiry_detection_source' => null,
                        'expiry_scanned_at' => null,
                        'expiry_verified_at' => now(),
                        'expiry_verified_by' => auth()->id(),
                    ]);

                    continue;
                }

                $expiry = $submittedExpiry[$document->id] ?? null;

                $document->update([
                    'expiry_date' => filled($expiry) ? $expiry : null,
                    'expiry_detection_status' => filled($expiry)
                        ? 'verified'
                        : 'verified_no_expiry',
                    'expiry_verified_at' => now(),
                    'expiry_verified_by' => auth()->id(),
                ]);
            }

            $branch->update([
                'verification_status' => 'verified',
                'verification_remarks' => null,
                'verified_at' => now(),
                'verified_by' => auth()->id(),
                // Approved, so the old-branch deadline no longer applies.
                'verification_due_at' => null,
            ]);
        });

        $this->notifyOwner(
            $branch->spa,
            $branch->name . ' is approved',
            $branch->name . ' has been verified and can now operate. Its pages are unlocked and it can be listed for customers.',
            true
        );

        return redirect()
            ->route('admin.registered-spas.index')
            ->with(
                'success',
                $branch->name . ' was approved and can now operate.'
            );
    }

    private function formatDocument(
        SpaVerificationDocument $document,
        bool $shared
    ): array {
        return [
            'id' => $document->id,
            'document_type' => $document->document_type,
            'shared' => $shared,
            'file_name' => $document->file_name,
            'file_url' => route('verification-documents.show', $document),
            'uploaded_at' => $document->created_at?->format('M d, Y h:i A'),

            'owner_expiry_date' => $document->owner_expiry_date?->format('Y-m-d'),
            'owner_expiry_date_display' => $document->owner_expiry_date?->format('M d, Y'),

            'ocr_expiry_date' => $document->ocr_expiry_date?->format('Y-m-d'),
            'ocr_expiry_date_display' => $document->ocr_expiry_date?->format('M d, Y'),

            'expiry_date' => $document->expiry_date?->format('Y-m-d'),
            'expiry_date_display' => $document->expiry_date?->format('M d, Y'),

            'expiry_date_raw' => $document->expiry_date_raw,
            'expiry_detection_status' => $document->expiry_detection_status,
            'expiry_detection_source' => $document->expiry_detection_source,
            'expiry_scanned_at' => $document->expiry_scanned_at?->format('M d, Y h:i A'),

            'expiry_verified_at' => $document->expiry_verified_at?->format('M d, Y h:i A'),
            'expiry_verified_by' => $document->expiry_verified_by,
        ];
    }

    /**
     * Emails the owner the result of this review. A mail problem is
     * logged and never undoes the administrator's decision.
     */
    private function notifyOwner(
        ?Spa $spa,
        string $heading,
        string $body,
        bool $approved,
        ?string $remarks = null
    ): void {
        $spa?->loadMissing('owner');

        $email = $spa?->owner?->email;

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new VerificationReviewResult(
                $spa,
                $heading,
                $body,
                $approved,
                $remarks
            ));
        } catch (\Throwable $e) {
            Log::error(
                'Failed to send verification review email to spa ' .
                $spa->id . ': ' . $e->getMessage()
            );
        }
    }
}