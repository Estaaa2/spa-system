<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Spa;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RegisteredSpaController extends Controller
{
    private const STATUSES = [
        'pending',
        'verified',
        'rejected',
        'unverified',
    ];

    private const REQUIRED_DOCUMENTS = [
        'government_id',
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

    private const PLANS = [
        'basic',
        'premium',
        'business',
    ];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $requestedStatus = $request->query('status');

        $status = in_array(
            $requestedStatus,
            self::STATUSES,
            true
        )
            ? $requestedStatus
            : null;

        $spas = Spa::query()
            ->with('owner')
            ->when(
                $status !== null,
                fn ($query) => $query->where(
                    'verification_status',
                    $status
                )
            )
            ->when(
                $q !== '',
                function ($query) use ($q) {
                    $search = '%' . addcslashes($q, '%_') . '%';

                    $query->where(function ($match) use ($search) {
                        $match
                            ->where('name', 'like', $search)
                            ->orWhereHas('owner', function ($owner) use ($search) {
                                $owner->where(function ($name) use ($search) {
                                    $name
                                        ->where('first_name', 'like', $search)
                                        ->orWhere('last_name', 'like', $search)
                                        ->orWhere('email', 'like', $search);
                                });
                            });
                    });
                }
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $counts = Spa::query()
            ->selectRaw('verification_status, COUNT(*) as total')
            ->groupBy('verification_status')
            ->pluck('total', 'verification_status');

        return view('admin.registered-spas.index', [
            'spas' => $spas,
            'q' => $q,
            'status' => $status,
            'statuses' => self::STATUSES,
            'counts' => $counts,
            'plans' => self::PLANS,
        ]);
    }

    public function edit(Spa $spa)
    {
        $spa->load([
            'owner',
            'verificationDocuments',
            'verifier',
        ]);

        $plan = strtolower((string) ($spa->business_tier ?: 'basic'));

        // Legacy Professional records are now displayed as Premium.
        if ($plan === 'professional') {
            $plan = 'premium';
        }

        if (! in_array($plan, self::PLANS, true)) {
            $plan = 'basic';
        }

        return response()->json([
            'spa' => [
                'id' => $spa->id,
                'name' => $spa->name,
                'business_tier' => $plan,
                'verification_status' => $spa->verification_status,
                'verification_remarks' => $spa->verification_remarks,

                'verified_at' => $spa->verified_at
                    ? Carbon::parse($spa->verified_at)->format('F d, Y h:i A')
                    : null,

                'owner_name' => $spa->owner?->name ?? 'N/A',
                'owner_email' => $spa->owner?->email ?? 'N/A',
                'verified_by' => $spa->verifier?->name,

                'documents' => $spa->verificationDocuments
                    ->map(function ($document) {
                        return [
                            'id' => $document->id,
                            'document_type' => $document->document_type,
                            'file_name' => $document->file_name,
                            'file_url' => asset(
                                'storage/' . ltrim($document->file_path, '/')
                            ),
                            'uploaded_at' => $document->created_at?->format(
                                'M d, Y h:i A'
                            ),

                            'owner_expiry_date' => $document->owner_expiry_date?->format(
                                'Y-m-d'
                            ),
                            'owner_expiry_date_display' => $document->owner_expiry_date?->format(
                                'M d, Y'
                            ),

                            'ocr_expiry_date' => $document->ocr_expiry_date?->format(
                                'Y-m-d'
                            ),
                            'ocr_expiry_date_display' => $document->ocr_expiry_date?->format(
                                'M d, Y'
                            ),

                            'expiry_date' => $document->expiry_date?->format(
                                'Y-m-d'
                            ),
                            'expiry_date_display' => $document->expiry_date?->format(
                                'M d, Y'
                            ),

                            'expiry_date_raw' => $document->expiry_date_raw,
                            'expiry_detection_status' => $document->expiry_detection_status,
                            'expiry_detection_source' => $document->expiry_detection_source,
                            'expiry_scanned_at' => $document->expiry_scanned_at?->format(
                                'M d, Y h:i A'
                            ),

                            'expiry_verified_at' => $document->expiry_verified_at?->format(
                                'M d, Y h:i A'
                            ),
                            'expiry_verified_by' => $document->expiry_verified_by,
                        ];
                    })
                    ->values(),
            ],
        ]);
    }

    public function update(Request $request, Spa $spa)
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

        $status = $validated['verification_status'];

        if ($status === 'rejected') {
            $spa->update([
                'verification_status' => 'rejected',
                'verification_remarks' => trim(
                    (string) $validated['verification_remarks']
                ),
                'verified_at' => null,
                'verified_by' => null,
            ]);

            return redirect()
                ->route('admin.registered-spas.index')
                ->with(
                    'success',
                    'Spa verification rejected. The owner must correct and resubmit the documents.'
                );
        }

        $documents = $spa
            ->verificationDocuments()
            ->get();

        $uploadedTypes = $documents
            ->pluck('document_type')
            ->unique()
            ->values()
            ->all();

        $missingDocuments = array_diff(
            self::REQUIRED_DOCUMENTS,
            $uploadedTypes
        );

        if ($missingDocuments !== []) {
            $missingLabels = collect($missingDocuments)
                ->map(
                    fn ($type) => self::DOCUMENT_LABELS[$type] ?? $type
                )
                ->implode(', ');

            return back()
                ->withInput()
                ->with(
                    'error',
                    'This spa cannot be verified because these required documents are missing: ' .
                    $missingLabels .
                    '.'
                );
        }

        $submittedExpiry = $validated['document_expiry'] ?? [];

        $businessPermit = $documents->firstWhere(
            'document_type',
            'business_permit'
        );

        $businessPermitExpiry = $businessPermit
            ? ($submittedExpiry[$businessPermit->id] ?? null)
            : null;

        if (! filled($businessPermitExpiry)) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'Confirm the Business Permit expiration date before approving this spa.'
                );
        }

        foreach ($documents as $document) {
            // BIR certificates do not require an expiry date.
            if ($document->document_type === 'bir_certificate') {
                continue;
            }

            $expiry = $submittedExpiry[$document->id] ?? null;

            if (! filled($expiry)) {
                continue;
            }

            $expiryDate = Carbon::createFromFormat(
                'Y-m-d',
                $expiry
            )->startOfDay();

            if ($expiryDate->isBefore(today())) {
                $label = self::DOCUMENT_LABELS[
                    $document->document_type
                ] ?? $document->document_type;

                return back()
                    ->withInput()
                    ->with(
                        'error',
                        "{$label} cannot be approved because its expiration date has already passed."
                    );
            }
        }

        DB::transaction(function () use (
            $spa,
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

                if (filled($expiry)) {
                    $document->update([
                        'expiry_date' => $expiry,
                        'expiry_detection_status' => 'verified',
                        'expiry_verified_at' => now(),
                        'expiry_verified_by' => auth()->id(),
                    ]);

                    continue;
                }

                $document->update([
                    'expiry_date' => null,
                    'expiry_detection_status' => 'verified_no_expiry',
                    'expiry_verified_at' => now(),
                    'expiry_verified_by' => auth()->id(),
                ]);
            }

            $spa->update([
                'verification_status' => 'verified',
                'verification_remarks' => null,
                'verified_at' => now(),
                'verified_by' => auth()->id(),
            ]);
        });

        return redirect()
            ->route('admin.registered-spas.index')
            ->with(
                'success',
                'Spa verified successfully. The owner can now continue to Subscription & Billing.'
            );
    }

    public function destroy(Spa $spa)
    {
        $spa->delete();

        return redirect()
            ->route('admin.registered-spas.index')
            ->with(
                'success',
                'Spa deleted successfully.'
            );
    }
}
