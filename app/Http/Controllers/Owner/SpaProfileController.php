<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\SpaVerificationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
            return back()->with(
                'error',
                'Verified spa profiles can no longer be edited.'
            );
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
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

    public function uploadDocument(Request $request)
    {
        $spa = Auth::user()->spa;

        if ($spa->verification_status === 'verified') {
            return back()->with(
                'error',
                'Documents are locked because this spa is already verified.'
            );
        }

        $documents = $request->file('documents', []);

        if (
            !is_array($documents) ||
            collect($documents)->filter()->isEmpty()
        ) {
            return back()->with(
                'error',
                'Please select at least one document before submitting.'
            );
        }

        $request->validate([
            'documents' => [
                'required',
                'array:government_id,dti_sec,bir_certificate',
            ],

            'documents.government_id' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],

            'documents.dti_sec' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],

            'documents.bir_certificate' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],
        ]);

        $allowedTypes = [
            'government_id',
            'dti_sec',
            'bir_certificate',
        ];

        $uploadedCount = 0;

        foreach ($documents as $type => $file) {

            if (!$file) {
                continue;
            }

            if (!in_array($type, $allowedTypes, true)) {
                continue;
            }

            $newPath = $file->store(
                'spa-verification-documents',
                'public'
            );

            $existingDocument = $spa
                ->verificationDocuments()
                ->where('document_type', $type)
                ->first();

            $spa->verificationDocuments()->updateOrCreate(
                [
                    'document_type' => $type,
                ],
                [
                    'file_path' => $newPath,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]
            );

            if (
                $existingDocument &&
                $existingDocument->file_path &&
                $existingDocument->file_path !== $newPath
            ) {
                Storage::disk('public')->delete(
                    $existingDocument->file_path
                );
            }

            $uploadedCount++;
        }

        if ($uploadedCount === 0) {
            return back()->with(
                'error',
                'No valid documents were selected.'
            );
        }

        $spa->load('verificationDocuments');

        $requiredDocuments = [
            'government_id',
            'dti_sec',
            'bir_certificate',
        ];

        $uploadedDocuments = $spa
            ->verificationDocuments()
            ->pluck('document_type')
            ->unique()
            ->toArray();

        $hasAllDocuments =
            count(
                array_intersect(
                    $requiredDocuments,
                    $uploadedDocuments
                )
            ) === count($requiredDocuments);

        $spa->update([
            'verification_status' => $hasAllDocuments
                ? 'pending'
                : 'unverified',

            'verification_remarks' => null,
            'verified_at' => null,
            'verified_by' => null,
        ]);

        if ($uploadedCount === 1) {
            return back()->with(
                'success',
                'Verification document updated successfully.'
            );
        }

        return back()->with(
            'success',
            "{$uploadedCount} verification documents updated successfully."
        );
    }

    public function destroyDocument(
        SpaVerificationDocument $document
    ) {
        $spa = Auth::user()->spa;

        if ((int) $document->spa_id !== (int) $spa->id) {
            abort(403);
        }

        if ($spa->verification_status === 'verified') {
            return back()->with(
                'error',
                'Documents are locked because this spa is already verified.'
            );
        }

        if (
            $document->file_path &&
            Storage::disk('public')->exists($document->file_path)
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
