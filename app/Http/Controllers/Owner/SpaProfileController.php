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
        'dti_sec',
        'business_permit',
    ];

    private const RENEWAL_WINDOW_DAYS = 30;

    private const DOCUMENT_LABELS = [
        'government_id' => 'Government ID',
        'dti_sec' => 'DTI / SEC Certificate',
        'bir_certificate' => 'BIR Certificate of Registration',
        'business_permit' => 'Business Permit',
    ];

    public function edit()
    {
        $spa = Auth::user()
            ->spa()
            ->with('verificationDocuments')
            ->firstOrFail();

        return view(
            'owner.spa-profile.edit',
            compact('spa')
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
         * Verified owners may only renew DTI/SEC and
         * Business Permit documents.
         */
        if ($wasVerified) {
            $blockedTypes = array_diff(
                $submittedTypes,
                self::RENEWABLE_DOCUMENTS
            );

            if (!empty($blockedTypes)) {
                return back()->with(
                    'error',
                    'Only DTI / SEC Certificate and Business Permit can be renewed.'
                );
            }

            foreach ($submittedTypes as $type) {
                $currentDocument = $spa
                    ->verificationDocuments()
                    ->where('document_type', $type)
                    ->first();

                if (
                    !$currentDocument ||
                    !$currentDocument->expiry_date
                ) {
                    return back()->with(
                        'error',
                        self::DOCUMENT_LABELS[$type] .
                        ' is not currently eligible for renewal.'
                    );
                }

                $renewalOpens = Carbon::parse(
                    $currentDocument->expiry_date
                )
                    ->subDays(self::RENEWAL_WINDOW_DAYS)
                    ->startOfDay();

                if (today()->lt($renewalOpens)) {
                    return back()->with(
                        'error',
                        self::DOCUMENT_LABELS[$type] .
                        ' can only be renewed within 30 days of expiration.'
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
             * Manual expiry input is only required when
             * a verified owner renews a Business Permit.
             */
            if (
                $wasVerified &&
                $type === 'business_permit'
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
                'public'
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
                        Storage::disk('public')->path($newPath),
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
                    Storage::disk('public')
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

                DB::transaction(function () use (
                    $spa,
                    $type,
                    $file,
                    $newPath,
                    $storedOwnerExpiry,
                    $scanResult
                ) {
                    $existingDocument = $spa
                        ->verificationDocuments()
                        ->where('document_type', $type)
                        ->lockForUpdate()
                        ->first();

                    /*
                     * Save the previous document in history
                     * before replacing it.
                     */
                    if ($existingDocument) {
                        SpaVerificationDocumentHistory::create([
                            'spa_id' =>
                                $spa->id,

                            'document_type' =>
                                $existingDocument->document_type,

                            'file_path' =>
                                $existingDocument->file_path,

                            'file_name' =>
                                $existingDocument->file_name,

                            'mime_type' =>
                                $existingDocument->mime_type,

                            'file_size' =>
                                $existingDocument->file_size,

                            'owner_expiry_date' =>
                                $existingDocument->owner_expiry_date,

                            'ocr_expiry_date' =>
                                $existingDocument->ocr_expiry_date,

                            'expiry_date' =>
                                $existingDocument->expiry_date,

                            'expiry_date_raw' =>
                                $existingDocument->expiry_date_raw,

                            'expiry_detection_status' =>
                                $existingDocument->expiry_detection_status,

                            'expiry_detection_source' =>
                                $existingDocument->expiry_detection_source,

                            'expiry_scanned_at' =>
                                $existingDocument->expiry_scanned_at,

                            'expiry_verified_at' =>
                                $existingDocument->expiry_verified_at,

                            'expiry_verified_by' =>
                                $existingDocument->expiry_verified_by,

                            'replaced_at' =>
                                now(),

                            'replaced_by' =>
                                Auth::id(),
                        ]);
                    }

                    $spa->verificationDocuments()
                        ->updateOrCreate(
                            [
                                'document_type' => $type,
                            ],
                            [
                                'file_path' =>
                                    $newPath,

                                'file_name' =>
                                    $file->getClientOriginalName(),

                                'mime_type' =>
                                    $file->getMimeType(),

                                'file_size' =>
                                    $file->getSize(),

                                'owner_expiry_date' =>
                                    $storedOwnerExpiry,

                                'ocr_expiry_date' =>
                                    $scanResult['expiry_date'],

                                /*
                                 * Admin approval sets the final
                                 * verified expiry date later.
                                 */
                                'expiry_date' =>
                                    null,

                                'expiry_date_raw' =>
                                    $scanResult['expiry_date_raw'],

                                'expiry_detection_status' =>
                                    $scanResult[
                                        'expiry_detection_status'
                                    ],

                                'expiry_detection_source' =>
                                    $scanResult[
                                        'expiry_detection_source'
                                    ],

                                'expiry_scanned_at' =>
                                    $scanResult[
                                        'expiry_scanned_at'
                                    ],

                                'expiry_verified_at' =>
                                    null,

                                'expiry_verified_by' =>
                                    null,
                            ]
                        );
                });

                $uploadedCount++;
            } catch (Throwable $e) {
                /*
                 * Delete only the newly uploaded file.
                 * Existing valid documents remain unchanged.
                 */
                Storage::disk('public')
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

        $uploadedDocuments = $spa
            ->verificationDocuments()
            ->pluck('document_type')
            ->unique()
            ->toArray();

        $hasAllDocuments = count(
            array_intersect(
                self::REQUIRED_DOCUMENTS,
                $uploadedDocuments
            )
        ) === count(self::REQUIRED_DOCUMENTS);

        /*
         * If documents are still incomplete, do not submit
         * the spa for review.
         */
        if (!$hasAllDocuments) {
            if (!$wasVerified) {
                $spa->update([
                    'verification_status' =>
                        $spa->verification_status === 'rejected'
                            ? 'rejected'
                            : 'unverified',

                    'verified_at' => null,
                    'verified_by' => null,
                ]);
            }

            return back()->with(
                'success',
                'Document changes were saved. Upload all required documents before submitting for review.'
            );
        }

        /*
         * Combined-button behavior:
         *
         * All documents exist and all selected files passed:
         * submit immediately for administrator review.
         */
        if (!$wasVerified) {
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

        /*
         * Verified owners are renewing documents. Renewal
         * should be handled as a replacement, not initial setup.
         */
        return back()->with(
            'success',
            'Document changes saved successfully.'
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
            Storage::disk('public')->exists(
                $document->file_path
            )
        ) {
            Storage::disk('public')->delete(
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
}