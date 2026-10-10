<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\SpaVerificationDocument;
use App\Models\SpaVerificationDocumentHistory;
use App\Services\VerificationDocumentScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\UploadedFile;
use App\Models\Branch;
use App\Models\Spa;
use Illuminate\Validation\Rule;
use Throwable;

class SpaProfileController extends Controller
{
    private const REQUIRED_DOCUMENTS = [
        'government_id',
        'dti_sec',
        'bir_certificate',
        'business_permit',
    ];

    private const RENEWABLE_DOCUMENTS = [
        'government_id',
        'dti_sec',
        'business_permit',
    ];

    private const RENEWAL_WINDOW_DAYS = 30;

    /** Documents an additional branch may upload for itself. */
    private const BRANCH_UPLOAD_TYPES = [
        'dti_sec',
        'bir_certificate',
        'business_permit',
    ];

    private const DOCUMENT_LABELS = [
        'government_id' => 'Government ID',
        'dti_sec' => 'DTI / SEC Certificate',
        'bir_certificate' => 'BIR Certificate of Registration',
        'business_permit' => 'Business Permit',
    ];

    public function edit(Request $request)
    {
        $user = Auth::user();

        $spa = $user
            ->spa()
            ->with('verificationDocuments')
            ->firstOrFail();

        // Opened from a branch card: switch to that branch first.
        if ($request->filled('branch')) {
            $requested = $spa->branches()
                ->find($request->integer('branch'));

            if ($requested) {
                session(['current_branch_id' => $requested->id]);
            }

            return redirect()->route('owner.spa-profile.edit');
        }

        $mainBranchId = $spa->mainBranch()?->id;

        // The branch chosen in the branch switcher.
        $selectedBranch = $spa->branches()
            ->with('verificationDocuments')
            ->find($user->currentBranchId());

        // An additional branch shows its own documents.
        // The main branch keeps the original screen.
        $showBranchDocuments =
            $spa->verification_status === 'verified' &&
            $selectedBranch &&
            (int) $selectedBranch->id !== (int) $mainBranchId;

        return view(
            'owner.spa-profile.edit',
            compact(
                'spa',
                'mainBranchId',
                'selectedBranch',
                'showBranchDocuments'
            )
        );
    }

    public function update(Request $request)
    {
        $spa = Auth::user()->spa;

        if ($spa->verification_status === 'verified') {
            return back()->with(
                'error',
                'Verified spa profiles can no longer be edited.'
            );
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('spas', 'name')
                    ->ignore($spa->id),
            ],
        ], [
            'name.unique' =>
                'This spa business name is already registered.',
        ]);

        if ($spa->name === $validated['name']) {
            return back()->with(
                'error',
                'No changes were made to the spa profile.'
            );
        }

        $spa->update([
            'name' => $validated['name'],
        ]);

        return back()->with(
            'success',
            'Spa profile updated successfully.'
        );
    }


    public function uploadDocument(
        Request $request,
        VerificationDocumentScanner $scanner
    ) {
        $spa = Auth::user()->spa;

        if ($spa->verification_status === 'pending') {
            return back()->with(
                'error',
                'Your documents are currently under review and cannot be changed.'
            );
        }

        $wasVerified =
            $spa->verification_status === 'verified';

        $documents = $request->file('documents', []);

        if (
            !is_array($documents) ||
            collect($documents)->filter()->isEmpty()
        ) {
            return back()->with(
                'error',
                'Please select at least one document before submitting for review.'
            );
        }

        $validatedExpiry = $request->input(
            'owner_expiry',
            []
        );

        $submittedTypes = collect($documents)
            ->filter()
            ->keys()
            ->filter(fn ($type) =>
                in_array(
                    $type,
                    self::REQUIRED_DOCUMENTS,
                    true
                )
            )
            ->values()
            ->all();

        /*
         * A verified spa may only upload a document that is missing,
         * was sent back by the administrator, or is due for renewal.
         */
        if ($wasVerified) {
            foreach ($submittedTypes as $type) {
                $currentDocument = $this
                    ->documentQuery(
                        $spa,
                        $type,
                        $this->documentBranchId($spa, $type)
                    )
                    ->first();

                $blockReason = $this->renewalBlockReason(
                    $currentDocument,
                    $type
                );

                if ($blockReason) {
                    return back()->with(
                        'error',
                        self::DOCUMENT_LABELS[$type] . ' ' . $blockReason
                    );
                }
            }
        }

        $uploadedCount = 0;
        $processingErrors = [];

        foreach ($documents as $type => $file) {
            if (
                !$file ||
                !in_array(
                    $type,
                    self::REQUIRED_DOCUMENTS,
                    true
                )
            ) {
                continue;
            }

            $ownerExpiry =
                $validatedExpiry[$type] ?? null;

            $rules = [
                'document' => [
                    'required',
                    'file',
                    'mimes:pdf,jpg,jpeg,png',
                    'max:10240',
                ],

                'owner_expiry' => [
                    'nullable',
                    'date_format:Y-m-d',
                    'after_or_equal:today',
                ],
            ];

            /*
             * A verified owner must type the new expiration date of
             * a Government ID or Business Permit. The administrator
             * still confirms it.
             */
            if (
                $wasVerified &&
                in_array(
                    $type,
                    ['government_id', 'business_permit'],
                    true
                )
            ) {
                $rules['owner_expiry'][] = 'required';
            }

            /*
             * BIR has no expiry-date requirement.
             */
            if ($type === 'bir_certificate') {
                $ownerExpiry = null;
                $rules['owner_expiry'] = ['nullable'];
            }

            $validator = Validator::make(
                [
                    'document' => $file,
                    'owner_expiry' => $ownerExpiry,
                ],
                $rules,
                [
                    'document.required' =>
                        self::DOCUMENT_LABELS[$type] .
                        ' is required.',

                    'document.mimes' =>
                        self::DOCUMENT_LABELS[$type] .
                        ' must be a PDF, JPG, JPEG, or PNG file.',

                    'document.max' =>
                        self::DOCUMENT_LABELS[$type] .
                        ' must not exceed 10 MB.',

                    'owner_expiry.required' =>
                        'Enter the expiration date shown on the ' .
                        self::DOCUMENT_LABELS[$type] .
                        '.',

                    'owner_expiry.after_or_equal' =>
                        'The expiration date for ' .
                        self::DOCUMENT_LABELS[$type] .
                        ' cannot already be expired.',
                ]
            );

            /*
             * Each file is validated independently.
             * Valid files can still be saved if another file fails.
             */
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $processingErrors[
                        "documents.$type"
                    ][] = $message;
                }

                continue;
            }

            $newPath = $file->store(
                'spa-verification-documents',
                'local'
            );

            try {
                /*
                 * BIR does not require expiry-date OCR.
                 */
                if ($type === 'bir_certificate') {
                    $scanResult = [
                        'expiry_date' => null,
                        'expiry_date_raw' => null,
                        'expiry_detection_status' => 'not_required',
                        'expiry_detection_source' => null,
                        'expiry_scanned_at' => null,
                    ];
                } else {
                    $scanResult = $scanner->scan(
                        Storage::disk('local')->path($newPath),
                        $file->getMimeType()
                    );
                }

                /*
                 * During initial setup, Government ID and
                 * Business Permit must have a readable expiry
                 * date detected by OCR.
                 */
                if (
                    !$wasVerified &&
                    in_array(
                        $type,
                        [
                            'government_id',
                            'business_permit',
                        ],
                        true
                    ) &&
                    (
                        ($scanResult[
                            'expiry_detection_status'
                        ] ?? null) !== 'detected' ||
                        empty($scanResult['expiry_date'])
                    )
                ) {
                    Storage::disk('local')
                        ->delete($newPath);

                    $processingErrors[
                        "documents.$type"
                    ][] =
                        self::DOCUMENT_LABELS[$type] .
                        ' is unreadable or its expiration date could not be detected. ' .
                        'Please upload a clearer copy showing the complete document and expiration date.';

                    continue;
                }

                $storedOwnerExpiry = $wasVerified
                    ? $ownerExpiry
                    : $scanResult['expiry_date'];

                /*
                 * For a verified spa the last confirmed expiration
                 * date stays in effect until the administrator
                 * confirms the new document.
                 */
                $this->replaceDocument(
                    $spa,
                    $this->documentBranchId($spa, $type),
                    $type,
                    $file,
                    $newPath,
                    $storedOwnerExpiry,
                    $scanResult,
                    $wasVerified
                );

                $uploadedCount++;
            } catch (Throwable $e) {
                /*
                 * Delete only the newly uploaded file.
                 * Existing valid documents remain unchanged.
                 */
                Storage::disk('local')
                    ->delete($newPath);

                report($e);

                $processingErrors[
                    "documents.$type"
                ][] =
                    self::DOCUMENT_LABELS[$type] .
                    ' could not be processed. Please try uploading it again.';
            }
        }

        /*
         * If a selected file failed, preserve all valid uploads
         * and keep the spa rejected/unverified.
         */
        if ($processingErrors) {
            return back()
                ->withErrors($processingErrors)
                ->withInput()
                ->with(
                    'error',
                    $uploadedCount > 0
                        ? 'Valid documents were saved. Please correct the highlighted document(s) and try again.'
                        : 'Please correct the highlighted document(s) and try again.'
                );
        }

        if ($uploadedCount === 0) {
            return back()->with(
                'error',
                'No valid documents were selected.'
            );
        }

        /*
         * A verified spa stays verified. The uploaded document
         * waits in the administrator's document review list.
         */
        if ($wasVerified) {
            return back()->with(
                'success',
                'Your document was submitted for administrator review. Any expiration date already confirmed stays in effect until it is approved.'
            );
        }

        $hasAllDocuments = $spa->hasCompleteVerificationDocuments();

        /*
         * If documents are still incomplete, do not submit
         * the spa for review.
         */
        if (!$hasAllDocuments) {
            $spa->update([
                'verification_status' =>
                    $spa->verification_status === 'rejected'
                        ? 'rejected'
                        : 'unverified',

                'verified_at' => null,
                'verified_by' => null,
            ]);

            return back()->with(
                'success',
                'Document changes were saved. Upload all required documents before submitting for review.'
            );
        }

        /*
         * All documents exist and all selected files passed:
         * submit immediately for administrator review.
         */
        $spa->update([
            'verification_status' => 'pending',
            'verification_remarks' => null,
            'verified_at' => null,
            'verified_by' => null,
        ]);

        return redirect()
            ->route('owner.onboarding.waiting')
            ->with(
                'success',
                'Your documents were submitted successfully. An administrator will review them within 24 hours.'
            );
    }

    /**
     * Upload the documents of one additional branch.
     *
     * A branch that is not approved yet is submitted for review once it
     * has everything it needs. A branch that is already approved stays
     * approved: its new document waits for the administrator instead.
     */
    public function uploadBranchDocuments(
        Request $request,
        Branch $branch,
        VerificationDocumentScanner $scanner
    ) {
        $spa = Auth::user()->spa;

        if (!$spa || (int) $branch->spa_id !== (int) $spa->id) {
            abort(403);
        }

        if ($spa->verification_status !== 'verified') {
            return back()->with(
                'error',
                'Your spa must be verified before you can submit documents for another branch.'
            );
        }

        if ((int) $spa->mainBranch()?->id === (int) $branch->id) {
            return back()->with(
                'error',
                'Use the Verification Documents section for your main branch.'
            );
        }

        if ($branch->verification_status === 'pending') {
            return back()->with(
                'error',
                $branch->name . ' is under review and its documents cannot be changed.'
            );
        }

        $isRenewal = $branch->verification_status === 'verified';

        $submitted = $request->file('branch_documents', []);

        $files = collect(is_array($submitted) ? $submitted : [])
            ->filter()
            ->only(
                $isRenewal
                    ? Branch::BRANCH_DOCUMENT_TYPES
                    : self::BRANCH_UPLOAD_TYPES
            );

        if ($files->isEmpty()) {
            return back()->with(
                'error',
                'Please select at least one document before submitting.'
            );
        }

        if ($isRenewal) {
            foreach ($files->keys() as $type) {
                $blockReason = $this->renewalBlockReason(
                    $this->documentQuery(
                        $spa,
                        $type,
                        (int) $branch->id
                    )->first(),
                    $type
                );

                if ($blockReason) {
                    return back()->with(
                        'error',
                        self::DOCUMENT_LABELS[$type] . ' ' . $blockReason
                    );
                }
            }
        }

        $ownerExpiries = (array) $request->input('branch_owner_expiry', []);

        $uploadedCount = 0;
        $errors = [];

        foreach ($files as $type => $file) {
            $label = self::DOCUMENT_LABELS[$type];
            $errorKey = "branch_{$branch->id}_{$type}";

            // BIR has no expiry-date requirement.
            $ownerExpiry = $type === 'bir_certificate'
                ? null
                : ($ownerExpiries[$type] ?? null);

            $expiryRules = [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ];

            if ($isRenewal && $type === 'business_permit') {
                $expiryRules[] = 'required';
            }

            $validator = Validator::make(
                [
                    'document' => $file,
                    'owner_expiry' => $ownerExpiry,
                ],
                [
                    'document' => [
                        'required',
                        'file',
                        'mimes:pdf,jpg,jpeg,png',
                        'max:10240',
                    ],
                    'owner_expiry' => $expiryRules,
                ],
                [
                    'document.mimes' =>
                        $label . ' must be a PDF, JPG, JPEG, or PNG file.',

                    'document.max' =>
                        $label . ' must not exceed 10 MB.',

                    'owner_expiry.required' =>
                        'Enter the expiration date shown on the ' .
                        $label . '.',

                    'owner_expiry.after_or_equal' =>
                        'The expiration date for ' . $label .
                        ' cannot already be expired.',
                ]
            );

            if ($validator->fails()) {
                $errors[$errorKey] = $validator->errors()->all();

                continue;
            }

            $newPath = $file->store(
                'spa-verification-documents',
                'local'
            );

            try {
                $scanResult = $type === 'bir_certificate'
                    ? [
                        'expiry_date' => null,
                        'expiry_date_raw' => null,
                        'expiry_detection_status' => 'not_required',
                        'expiry_detection_source' => null,
                        'expiry_scanned_at' => null,
                    ]
                    : $scanner->scan(
                        Storage::disk('local')->path($newPath),
                        $file->getMimeType()
                    );

                // Same rule as the first submission: the permit's
                // expiry date must be readable. On a renewal the owner
                // types the date and the administrator confirms it.
                if (
                    !$isRenewal &&
                    $type === 'business_permit' &&
                    (
                        ($scanResult['expiry_detection_status'] ?? null) !== 'detected' ||
                        empty($scanResult['expiry_date'])
                    )
                ) {
                    Storage::disk('local')->delete($newPath);

                    $errors[$errorKey][] =
                        $label .
                        ' is unreadable or its expiration date could not be detected. ' .
                        'Please upload a clearer copy showing the complete document and expiration date.';

                    continue;
                }

                $this->replaceDocument(
                    $spa,
                    (int) $branch->id,
                    $type,
                    $file,
                    $newPath,
                    $ownerExpiry ?: $scanResult['expiry_date'],
                    $scanResult,
                    $isRenewal
                );

                $uploadedCount++;
            } catch (Throwable $e) {
                Storage::disk('local')->delete($newPath);

                report($e);

                $errors[$errorKey][] =
                    $label .
                    ' could not be processed. Please try uploading it again.';
            }
        }

        if ($errors) {
            return back()
                ->withErrors($errors, 'branchDocuments')
                ->with(
                    'error',
                    $uploadedCount > 0
                        ? 'Valid documents were saved. Please correct the highlighted document(s) and try again.'
                        : 'Please correct the highlighted document(s) and try again.'
                );
        }

        // An approved branch stays approved while its new document
        // waits in the administrator's document review list.
        if ($isRenewal) {
            return back()->with(
                'success',
                'Your document for ' . $branch->name .
                ' was submitted for administrator review. Any expiration date already confirmed stays in effect until it is approved.'
            );
        }

        if (!$branch->hasRequiredDocuments()) {
            return back()->with(
                'success',
                'Documents saved for ' . $branch->name .
                '. Upload both the BIR Certificate and the Business Permit to submit this branch for review.'
            );
        }

        $branch->update([
            'verification_status' => 'pending',
            'verification_remarks' => null,
            'verified_at' => null,
            'verified_by' => null,
        ]);

        return back()->with(
            'success',
            $branch->name . ' was submitted for review. An administrator will check its documents.'
        );
    }

    public function destroyDocument(
        SpaVerificationDocument $document
    ) {
        $spa = Auth::user()->spa;

        if (
            (int) $document->spa_id !==
            (int) $spa->id
        ) {
            abort(403);
        }

        if (
            in_array(
                $spa->verification_status,
                [
                    'pending',
                    'verified',
                ],
                true
            )
        ) {
            return back()->with(
                'error',
                'This document is currently locked.'
            );
        }

        if (
            $document->file_path &&
            Storage::disk('local')->exists(
                $document->file_path
            )
        ) {
            Storage::disk('local')->delete(
                $document->file_path
            );
        }

        $document->delete();

        $spa->update([
            'verification_status' => 'unverified',
            'verification_remarks' => null,
            'verified_at' => null,
            'verified_by' => null,
        ]);

        return back()->with(
            'success',
            'Document removed successfully.'
        );
    }

    /**
     * Where a document type lives for the spa's own (main) submission:
     * BIR and Business Permit belong to the main branch, while
     * Government ID and DTI/SEC belong to the spa (no branch).
     */
    private function documentBranchId(Spa $spa, string $type): ?int
    {
        if (!in_array($type, Branch::BRANCH_DOCUMENT_TYPES, true)) {
            return null;
        }

        return $spa->mainBranch()?->id;
    }

    /** One document slot: a type on a specific branch, or on the spa. */
    private function documentQuery(Spa $spa, string $type, ?int $branchId)
    {
        return $spa->verificationDocuments()
            ->where('document_type', $type)
            ->when(
                $branchId,
                fn ($query) => $query->where('branch_id', $branchId),
                fn ($query) => $query->whereNull('branch_id')
            );
    }

    /**
     * Why an already-verified spa or branch may NOT upload this document
     * right now, or null when the upload is allowed.
     *
     * Allowed: the document is missing, the administrator sent it back,
     * or its confirmed expiration date is 30 days away or already past.
     */
    private function renewalBlockReason(
        ?SpaVerificationDocument $document,
        string $type
    ): ?string {
        if (!$document) {
            return null;
        }

        if (filled($document->review_remarks)) {
            return null;
        }

        if (!$document->expiry_verified_at) {
            return 'is waiting for administrator review and cannot be changed yet.';
        }

        if (!in_array($type, self::RENEWABLE_DOCUMENTS, true)) {
            return 'is already verified and does not need to be renewed.';
        }

        if (!$document->expiry_date) {
            return 'has no expiration date on file, so it does not need to be renewed.';
        }

        $renewalOpens = Carbon::parse($document->expiry_date)
            ->subDays(self::RENEWAL_WINDOW_DAYS)
            ->startOfDay();

        if (today()->lt($renewalOpens)) {
            return 'can only be renewed within 30 days of expiration.';
        }

        return null;
    }

    /**
     * Saves a newly uploaded file into its slot. The document it
     * replaces, if any, is copied to the history table first.
     *
     * $keepConfirmedExpiry is true for a spa or branch that is already
     * verified: the last expiration date the administrator confirmed
     * stays in effect until the new document is confirmed.
     */
    private function replaceDocument(
        Spa $spa,
        ?int $branchId,
        string $type,
        UploadedFile $file,
        string $newPath,
        ?string $ownerExpiry,
        array $scanResult,
        bool $keepConfirmedExpiry = false
    ): void {
        DB::transaction(function () use (
            $spa,
            $branchId,
            $type,
            $file,
            $newPath,
            $ownerExpiry,
            $scanResult,
            $keepConfirmedExpiry
        ) {
            $existing = $this->documentQuery($spa, $type, $branchId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                SpaVerificationDocumentHistory::create([
                    ...$existing->only([
                        'spa_id',
                        'branch_id',
                        'document_type',
                        'file_path',
                        'file_name',
                        'mime_type',
                        'file_size',
                        'owner_expiry_date',
                        'ocr_expiry_date',
                        'expiry_date',
                        'expiry_date_raw',
                        'expiry_detection_status',
                        'expiry_detection_source',
                        'expiry_scanned_at',
                        'expiry_verified_at',
                        'expiry_verified_by',
                    ]),
                    'replaced_at' => now(),
                    'replaced_by' => Auth::id(),
                ]);
            }

            $document = $spa->verificationDocuments()->updateOrCreate(
                [
                    'branch_id' => $branchId,
                    'document_type' => $type,
                ],
                [
                    'file_path' => $newPath,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'owner_expiry_date' => $ownerExpiry,
                    'ocr_expiry_date' => $scanResult['expiry_date'],
                    // The admin sets the confirmed expiry date on approval.
                    'expiry_date' => $keepConfirmedExpiry
                        ? $existing?->expiry_date
                        : null,
                    'expiry_date_raw' => $scanResult['expiry_date_raw'],
                    'expiry_detection_status' => $scanResult['expiry_detection_status'],
                    'expiry_detection_source' => $scanResult['expiry_detection_source'],
                    'expiry_scanned_at' => $scanResult['expiry_scanned_at'],
                    'expiry_verified_at' => null,
                    'expiry_verified_by' => null,
                ]
            );

            // A fresh upload clears any earlier "sent back" note.
            $document->forceFill(['review_remarks' => null])->save();
        });
    }
}