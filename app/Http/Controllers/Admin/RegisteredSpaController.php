<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Spa;
use Illuminate\Http\Request;

class RegisteredSpaController extends Controller
{
    private const STATUSES = ['pending', 'verified', 'rejected', 'unverified'];

    public function index(Request $request)
    {
        $q      = trim((string) $request->get('q'));
        $status = in_array($request->get('status'), self::STATUSES, true) ? $request->get('status') : null;

        $spas = Spa::with('owner')
            ->when($status, fn ($query) => $query->where('verification_status', $status))
            ->when($q !== '', function ($query) use ($q) {
                // Grouped so the status filter still applies to every match.
                $query->where(function ($match) use ($q) {
                    $match->where('name', 'like', "%{$q}%")
                        ->orWhereHas('owner', function ($owner) use ($q) {
                            $owner->where(function ($name) use ($q) {
                                $name->where('first_name', 'like', "%{$q}%")
                                    ->orWhere('last_name', 'like', "%{$q}%")
                                    ->orWhere('email', 'like', "%{$q}%");
                            });
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Tab counts, one query: ['pending' => 3, 'verified' => 12, ...]
        $counts = Spa::selectRaw('verification_status, COUNT(*) as total')
            ->groupBy('verification_status')
            ->pluck('total', 'verification_status');

        return view('admin.registered-spas.index', [
            'spas'     => $spas,
            'q'        => $q,
            'status'   => $status,
            'statuses' => self::STATUSES,
            'counts'   => $counts,
        ]);
    }

    public function edit(Spa $spa)
    {
        $spa->load('owner', 'verificationDocuments', 'verifier');

        return response()->json([
            'spa' => [
                'id' => $spa->id,
                'name' => $spa->name,
                'verification_status' => $spa->verification_status,
                'verification_remarks' => $spa->verification_remarks,
                'verified_at' => optional($spa->verified_at)?->format('F d, Y h:i A'),
                'owner_name' => $spa->owner->name ?? 'N/A',
                'owner_email' => $spa->owner->email ?? 'N/A',
                'verified_by' => $spa->verifier->name ?? null,
                'documents' => $spa->verificationDocuments->map(function ($document) {
                    return [
                        'document_type' => $document->document_type,
                        'file_name' => $document->file_name,
                        'file_url' => asset('storage/' . $document->file_path),
                        'uploaded_at' => $document->created_at->format('F d, Y h:i A'),
                    ];
                })->values(),
            ]
        ]);
    }

    public function update(Request $request, Spa $spa)
    {
        $request->validate([
            'verification_status' => ['required', 'in:verified,rejected'],
            'verification_remarks' => ['nullable', 'string', 'required_if:verification_status,rejected'],
        ]);

        $status = $request->verification_status;

        if ($status === 'verified') {
            $requiredDocuments = [
                'government_id',
                'dti_sec',
                'bir_certificate',
                'business_permit',
            ];

            $uploadedDocuments = $spa
                ->verificationDocuments()
                ->pluck('document_type')
                ->unique()
                ->toArray();

            $missingDocuments = array_diff(
                $requiredDocuments,
                $uploadedDocuments
            );

            if (!empty($missingDocuments)) {
                return back()->with(
                    'error',
                    'This spa cannot be verified because one or more required documents are missing.'
                );
            }
        }

        $spa->update([
            'verification_status' => $status,
            'verification_remarks' => $status === 'rejected'
                ? $request->verification_remarks
                : null,
            'verified_at' => $status === 'verified'
                ? now()
                : null,
            'verified_by' => $status === 'verified'
                ? auth()->id()
                : null,
        ]);

        return redirect()
            ->route('admin.registered-spas.index')
            ->with(
                'success',
                $status === 'verified'
                    ? 'Spa verified successfully.'
                    : 'Spa verification rejected successfully.'
            );
    }

    public function destroy(Spa $spa)
    {
        $spa->delete();

        return redirect()
            ->route('admin.registered-spas.index')
            ->with('success', 'Spa deleted successfully.');
    }
}
