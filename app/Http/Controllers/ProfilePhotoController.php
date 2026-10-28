<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Identity\IdentityVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /profile-photos/{user}?v=…  — streams a profile photo from the private disk.
 *
 * Who may see it: IdentityPolicy::viewPhoto. Anyone else gets 404, so the route
 * does not reveal which accounts have a photo. Uploading and removing are
 * Phase 2 (staff profile page) and use IdentityVerificationService.
 */
class ProfilePhotoController extends Controller
{
    public function __construct(private IdentityVerificationService $identity)
    {
    }

    public function show(Request $request, User $user): Response
    {
        abort_unless(Gate::allows('view-profile-photo', $user), 404);

        $path = $user->profile_photo_path;
        $disk = $this->identity->disk();

        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, 'profile-photo.jpg', [
            'Content-Type'           => 'image/jpeg',
            // Private: browsers may cache it, shared caches may not. The URL
            // changes (?v=) whenever the photo changes.
            'Cache-Control'          => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
