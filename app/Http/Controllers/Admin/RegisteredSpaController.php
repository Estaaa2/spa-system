<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Spa;
use Illuminate\Http\Request;

class RegisteredSpaController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->q;

        $spas = Spa::with('owner')
            ->when($q, function($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhereHas('owner', function($q2) use ($q) {
                        $q2->where('name', 'like', "%{$q}%");
                    });
            })
            ->latest()
            ->paginate(10); // ← paginate instead of all()

        $pendingSpas = Spa::with('owner')
            ->where('verification_status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        $verifiedCount = Spa::where('verification_status', 'verified')->count();

        return view('admin.registered-spas.index', compact('spas', 'q', 'pendingSpas', 'verifiedCount'));
    }

    public function edit(Spa $spa)
    {
        $spa->load('owner', 'verificationDocuments', 'verifier');

        return response()->json([
            'spa' => [
                'id' => $spa->id,
                'name' => $spa->name,
                'business_tier' => $spa->business_tier,
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
            'business_tier' => ['required', 'in:basic,professional'],
            'verification_status' => ['required', 'in:verified,rejected'],
            'verification_remarks' => ['nullable', 'string', 'required_if:verification_status,rejected'],
        ]);

        $status = $request->verification_status;

        $spa->update([
            'business_tier' => $request->business_tier,
            'verification_status' => $status,
            'verification_remarks' => $status === 'rejected' ? $request->verification_remarks : null,
            'verified_at' => $status === 'verified' ? now() : null,
            'verified_by' => $status === 'verified' ? auth()->id() : null,
        ]);

        return redirect()->route('admin.registered-spas.index')->with('success', 'Spa updated successfully.');
    }

    public function destroy(Spa $spa)
    {
        $spa->delete();

        return redirect()
            ->route('admin.registered-spas.index')
            ->with('success', 'Spa deleted successfully.');
    }
}
