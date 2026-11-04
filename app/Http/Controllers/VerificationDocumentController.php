<?php

namespace App\Http\Controllers;

use App\Models\SpaVerificationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VerificationDocumentController extends Controller
{
    /**
     * Streams one verification document from private storage.
     *
     * Allowed: the owner of the spa the document belongs to,
     * and platform admins who can view registered spas.
     */
    public function show(Request $request, SpaVerificationDocument $document)
    {
        $user = $request->user();

        $isAdmin = $user->hasRole('admin')
            && $user->can('view registered spas');

        $isOwner = $user->hasRole('owner')
            && $user->spa_id !== null
            && (int) $user->spa_id === (int) $document->spa_id;

        abort_unless($isAdmin || $isOwner, 403);

        $disk = Storage::disk('local');

        abort_unless(
            $document->file_path && $disk->exists($document->file_path),
            404
        );

        return $disk->response(
            $document->file_path,
            $document->file_name ?: basename($document->file_path),
            [
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
