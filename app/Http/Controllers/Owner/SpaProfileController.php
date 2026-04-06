<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\SpaVerificationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SpaProfileController extends Controller
{
    public function edit()
    {
        $spa = Auth::user()
            ->spa()
            ->with('verificationDocuments')
            ->firstOrFail();

        return view('owner.spa-profile.edit', compact('spa'));
    }

    public function update(Request $request)
    {
        $spa = Auth::user()->spa;

        if ($spa->verification_status === 'verified') {
            return back()->with('error', 'Verified spa profiles can no longer be edited.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $spa->update([
            'name' => $validated['name'],
        ]);

        return back()->with('success', 'Spa profile updated successfully.');
    }

    public function uploadDocument(Request $request)
    {
        \Log::info('Upload debug', [
            'all_files' => $request->allFiles(),
            'has_documents' => $request->hasFile('documents'),
            'all_input' => $request->except(['_token']),
        ]);

        $spa = Auth::user()->spa;

        if ($spa->verification_status === 'verified') {
            return back()->with('error', 'Documents are locked because this spa is already verified.');
        }

        $request->validate([
            'documents.government_id' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'documents.dti_sec' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'documents.bir_certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $documents = $request->file('documents', []);

        foreach ($documents as $type => $file) {
            if (!$file) {
                continue;
            }

            $existingDocument = $spa->verificationDocuments()
                ->where('document_type', $type)
                ->first();

            if ($existingDocument) {
                Storage::disk('public')->delete($existingDocument->file_path);
            }

            $path = $file->store('spa-verification-documents', 'public');

            $spa->verificationDocuments()->updateOrCreate(
                ['document_type' => $type],
                [
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]
            );
        }

        $requiredDocuments = ['government_id', 'dti_sec', 'bir_certificate'];

        $uploadedDocuments = $spa->verificationDocuments()
            ->pluck('document_type')
            ->unique()
            ->toArray();

        $hasAllDocuments = count(array_intersect($requiredDocuments, $uploadedDocuments)) === count($requiredDocuments);

        $spa->update([
            'verification_status' => $hasAllDocuments ? 'pending' : 'unverified',
            'verification_remarks' => null,
            'verified_at' => null,
            'verified_by' => null,
        ]);

        return back()->with('success', 'Verification documents updated successfully.');
    }

    public function destroyDocument(SpaVerificationDocument $document)
    {
        $spa = Auth::user()->spa;

        if ($document->spa_id !== $spa->id) {
            abort(403);
        }

        if ($spa->verification_status === 'verified') {
            return back()->with('error', 'Documents are locked because this spa is already verified.');
        }

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        $spa->update([
            'verification_status' => 'unverified',
            'verification_remarks' => null,
            'verified_at' => null,
            'verified_by' => null,
        ]);

        return back()->with('success', 'Document removed successfully.');
    }
}