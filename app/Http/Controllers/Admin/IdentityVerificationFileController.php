<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdentityVerification;
use App\Services\Identity\IdentityVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /admin/identity-verifications/{verification}/files/{file}
 * file = id | selfie | photo
 *
 * Streams a submission's ID image, selfie or the profile photo it was submitted
 * with, for the platform admin's review page (Phase 3). Every view is logged in
 * identity_file_views. Files no longer exist once the request is decided (D9),
 * so this returns 404 afterwards.
 */
class IdentityVerificationFileController extends Controller
{
    public function __construct(private IdentityVerificationService $identity)
    {
    }

    public function show(Request $request, IdentityVerification $verification, string $file): Response
    {
        Gate::authorize('review-identity-verifications');

        $path = $file === IdentityVerification::FILE_PHOTO
            ? $verification->photo_path_reviewed
            : $verification->pathFor($file);

        $disk = $this->identity->disk();

        abort_unless($path && $disk->exists($path), 404, 'This file was deleted after the decision.');

        $this->identity->recordFileView($verification, $request->user(), $file, $request->ip());

        return $disk->response($path, "{$file}-{$verification->id}.jpg", [
            'Content-Type'           => 'image/jpeg',
            'Cache-Control'          => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy'        => 'no-referrer',
        ], 'inline');
    }
}
