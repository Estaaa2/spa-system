<?php

namespace App\Http\Controllers;

use App\Http\Requests\Identity\UpdateProfilePhotoRequest;
use App\Models\User;
use App\Services\Identity\IdentityVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Profile photos (private disk).
 *
 * GET    /profile-photos/{user}?v=…  stream a photo; who may see it: IdentityPolicy::viewPhoto.
 *                                    Anyone else gets 404, so the route does not reveal
 *                                    which accounts have a photo.
 * POST   /profile/photo              upload or replace your own photo
 * DELETE /profile/photo              remove your own photo (refused while verified or pending)
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

    public function update(UpdateProfilePhotoRequest $request): RedirectResponse
    {
        try {
            $result = $this->identity->updateProfilePhoto($request->user(), $request->file('photo'));
        } catch (ValidationException $e) {
            throw $e->errorBag('photo')->redirectTo(route('profile.edit').'#photo');
        }

        $message = match (true) {
            $result['was_verified']       => 'Photo updated. Because your photo changed, verify your identity again to keep receiving online home-service bookings.',
            $result['pending_superseded'] => 'Photo updated. Your verification request was closed because the photo changed. Please submit it again.',
            default                       => 'Profile photo updated.',
        };

        return redirect()->to(route('profile.edit').'#photo')->with('success', $message);
    }

    public function destroy(Request $request): RedirectResponse
    {
        try {
            $this->identity->removeProfilePhoto($request->user());
        } catch (ValidationException $e) {
            throw $e->errorBag('photo')->redirectTo(route('profile.edit').'#photo');
        }

        return redirect()->to(route('profile.edit').'#photo')->with('success', 'Profile photo removed.');
    }
}
