<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\VerificationReviewResult;
use App\Models\Branch;
use App\Models\Spa;
use App\Models\SpaVerificationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Review of ONE document that belongs to a spa or branch that is already
 * verified: a renewal, a replacement, or a required document that was
 * missing. The spa and the branch keep their status either way.
 */
class DocumentReviewController extends Controller
{
    private const DOCUMENT_LABELS = [
        'government_id' => 'Government ID',
        'dti_sec' => 'DTI / SEC Certificate',
        'bir_certificate' => 'BIR Certificate of Registration',
        'business_permit' => 'Business Permit',
    ];

    private const PLANS = [
        'basic',
        'premium',
        'business',
    ];

    /**
     * One document, in the same JSON shape the spa review modal
     * already understands (so the modal is reused as-is).
     */
    public function edit(SpaVerificationDocument $document)
    {
        $spa = Spa::with('owner')->find($document->spa_id);

        abort_unless($spa, 404);

        $branch = $document->branch_id
            ? Branch::find($document->branch_id)
            : null;

        $label = self::DOCUMENT_LABELS[$document->document_type]
            ?? $document->document_type;

        $name = $spa->name . ' — ' . $label;

        if ($branch) {
            $name .= ' (' . $branch->name . ')';
        }

        if ($document->expiry_date) {
            $name .= ' · Date in effect now: ' .
                $document->expiry_date->format('M d, Y');
        }

        $plan = strtolower((string) ($spa->business_tier ?: 'basic'));

        if ($plan === 'professional') {
            $plan = 'premium';
        }

        if (! in_array($plan, self::PLANS, true)) {
            $plan = 'basic';
        }

        return response()->json([
            'spa' => [
                'id' => $document->id,
                'name' => $name,
                'business_tier' => $plan,
                'verification_status' => 'pending',
                'verification_remarks' => null,
                'verified_at' => null,
                'owner_name' => $spa->owner?->name ?? 'N/A',
                'owner_email' => $spa->owner?->email ?? 'N/A',
                'verified_by' => null,
                'documents' => [[
                    'id' => $document->id,
                    'document_type' => $document->document_type,
                    'file_name' => $document->file_name,
                    'file_url' => route('verification-documents.show', $document),
                    'uploaded_at' => $document->updated_at?->format('M d, Y h:i A'),

                    'owner_expiry_date' => $document->owner_expiry_date?->format('Y-m-d'),
                    'owner_expiry_date_display' => $document->owner_expiry_date?->format('M d, Y'),

                    'ocr_expiry_date' => $document->ocr_expiry_date?->format('Y-m-d'),
                    'ocr_expiry_date_display' => $document->ocr_expiry_date?->format('M d, Y'),

                    // Left empty on purpose: the date box is then filled
                    // with the date of the NEW file, not the old one.
                    'expiry_date' => null,
                    'expiry_date_display' => null,

                    'expiry_date_raw' => $document->expiry_date_raw,
                    'expiry_detection_status' => $document->expiry_detection_status,
                    'expiry_detection_source' => $document->expiry_detection_source,
                    'expiry_scanned_at' => $document->expiry_scanned_at?->format('M d, Y h:i A'),

                    'expiry_verified_at' => null,
                    'expiry_verified_by' => null,
                ]],
            ],
        ]);
    }

    /** Confirm or send back one document. Nothing else is touched. */
    public function update(Request $request, SpaVerificationDocument $document)
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

        if (
            $document->expiry_verified_at ||
            filled($document->review_remarks)
        ) {
            return back()->with(
                'error',
                'This document is not waiting for review.'
            );
        }

        $spa = Spa::find($document->spa_id);

        if (! $spa || $spa->verification_status !== 'verified') {
            return back()->with(
                'error',
                'This spa is not verified yet. Review it from the spa list instead.'
            );
        }

        // A document of an additional branch that is not approved yet is
        // reviewed together with that branch application.
        if ($document->branch_id) {
            $branch = Branch::find($document->branch_id);

            if (! $branch || $branch->verification_status !== 'verified') {
                return back()->with(
                    'error',
                    'This branch is not approved yet. Review it from Branch Applications instead.'
                );
            }
        }

        $label = self::DOCUMENT_LABELS[$document->document_type]
            ?? $document->document_type;

        if ($validated['verification_status'] === 'rejected') {
            // The confirmed expiration date (if any) is left as it is.
            $document->forceFill([
                'review_remarks' => trim(
                    (string) $validated['verification_remarks']
                ),
            ])->save();

            $this->notifyOwner(
                $spa,
                'Your ' . $label . ' was sent back',
                'The ' . $label . ' you uploaded was reviewed and could not be accepted. Please upload a corrected copy from your Spa Profile. Any expiration date already confirmed stays in effect.',
                false,
                $document->review_remarks
            );

            return redirect()
                ->route('admin.registered-spas.index')
                ->with(
                    'success',
                    $label . ' was sent back. The owner must upload it again.'
                );
        }

        if ($document->document_type === 'bir_certificate') {
            $document->forceFill([
                'expiry_date' => null,
                'expiry_date_raw' => null,
                'expiry_detection_status' => 'not_required',
                'expiry_detection_source' => null,
                'expiry_scanned_at' => null,
                'expiry_verified_at' => now(),
                'expiry_verified_by' => auth()->id(),
                'review_remarks' => null,
            ])->save();
        } else {
            $expiry = ($validated['document_expiry'] ?? [])[$document->id]
                ?? null;

            if (
                ! filled($expiry) &&
                in_array(
                    $document->document_type,
                    ['government_id', 'business_permit'],
                    true
                )
            ) {
                return back()->with(
                    'error',
                    'Confirm the ' . $label . ' expiration date before approving it.'
                );
            }

            if (
                filled($expiry) &&
                Carbon::createFromFormat('Y-m-d', $expiry)
                    ->startOfDay()
                    ->isBefore(today())
            ) {
                return back()->with(
                    'error',
                    "{$label} cannot be approved because its expiration date has already passed."
                );
            }

            $document->forceFill([
                'expiry_date' => filled($expiry) ? $expiry : null,
                'expiry_detection_status' => filled($expiry)
                    ? 'verified'
                    : 'verified_no_expiry',
                'expiry_verified_at' => now(),
                'expiry_verified_by' => auth()->id(),
                'review_remarks' => null,
            ])->save();
        }

        $document->refresh();

        $this->notifyOwner(
            $spa,
            'Your ' . $label . ' was confirmed',
            'The ' . $label . ' you uploaded has been confirmed' .
            (
                $document->expiry_date
                    ? ' and is valid until ' . $document->expiry_date->format('F d, Y') . '.'
                    : '.'
            ),
            true
        );

        return redirect()
            ->route('admin.registered-spas.index')
            ->with(
                'success',
                $label . ' was confirmed.'
            );
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