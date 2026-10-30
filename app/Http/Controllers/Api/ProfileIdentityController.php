<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\SubmitIdentityVerificationRequest;
use App\Http\Requests\Identity\UpdateProfilePhotoRequest;
use App\Models\IdentityVerification;
use App\Models\User;
use App\Services\Identity\IdentityVerificationService;
use App\Support\Identity\ConsentNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Mobile API for the staff profile photo and identity verification (Phase 6).
 * Same Form Requests, policy and service as the web profile page
 * (ProfilePhotoController, IdentityVerificationController); only the replies
 * are JSON. Endpoint reference: claude/staff-verification-phase6.md.
 *
 * GET    /api/me/identity                  state + everything the screen needs
 * POST   /api/me/photo                     multipart: photo
 * DELETE /api/me/photo
 * POST   /api/me/identity-verification     multipart: id_type, id_image, selfie, consent=1
 * DELETE /api/me/identity-verification     withdraw consent / cancel request
 */
class ProfileIdentityController extends Controller
{
    public function __construct(
        private IdentityVerificationService $identity,
        private AuthController $auth,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()));
    }

    public function updatePhoto(UpdateProfilePhotoRequest $request): JsonResponse
    {
        $result = $this->identity->updateProfilePhoto($request->user(), $request->file('photo'));

        $message = match (true) {
            $result['was_verified']       => 'Photo updated. Because your photo changed, verify your identity again to keep receiving online home-service bookings.',
            $result['pending_superseded'] => 'Photo updated. Your verification request was closed because the photo changed. Please submit it again.',
            default                       => 'Profile photo updated.',
        };

        return response()->json(['message' => $message] + $this->payload($request->user()));
    }

    public function destroyPhoto(Request $request): JsonResponse
    {
        $this->identity->removeProfilePhoto($request->user());

        return response()->json(['message' => 'Profile photo removed.'] + $this->payload($request->user()));
    }

    public function submit(SubmitIdentityVerificationRequest $request): JsonResponse
    {
        $this->identity->submit(
            $request->user(),
            $request->validated('id_type'),
            $request->file('id_image'),
            $request->file('selfie'),
            'api'
        );

        return response()->json(
            ['message' => 'Submitted for review. The Levictas team will check it, and the result will show here.'] + $this->payload($request->user()),
            201
        );
    }

    public function withdraw(Request $request): JsonResponse
    {
        $row = $this->identity->withdraw($request->user());

        if (! $row) {
            return response()->json(['message' => 'There is no verification request or approval to withdraw.'], 422);
        }

        $message = $row->reviewed_at === null
            ? 'Your request was cancelled and your ID and selfie were deleted.'
            : 'Consent withdrawn. You are no longer verified, so you won\'t be assigned new online home-service bookings.';

        return response()->json(['message' => $message] + $this->payload($request->user()));
    }

    /** `user` (same object as /api/me) + `identity`. */
    private function payload(User $user): array
    {
        $user = $user->fresh(['staff', 'roles', 'latestIdentityVerification']);
        $canVerify = Gate::forUser($user)->allows('submit-identity-verification');
        $latest = $user->latestIdentityVerification;

        return [
            'user'     => $this->auth->formatUser($user),
            'identity' => [
                'can_verify'   => $canVerify,
                // not_started | pending | approved | rejected | needs_reverification | withdrawn; null when can_verify is false
                'state'        => $canVerify ? $this->identity->stateFor($user) : null,
                'photo_locked' => $user->isIdentityVerified() || $latest?->status === IdentityVerification::STATUS_PENDING,
                'latest'       => $latest ? [
                    'status'           => $latest->status,
                    'id_type'          => $latest->id_type,
                    'id_type_label'    => config('identity.id_types.'.$latest->id_type, $latest->id_type),
                    'submitted_at'     => $latest->submitted_at?->toIso8601String(),
                    'reviewed_at'      => $latest->reviewed_at?->toIso8601String(),
                    'rejection_reason' => $latest->status === IdentityVerification::STATUS_REJECTED ? $latest->rejection_reason : null,
                    'superseded_reason' => $latest->status === IdentityVerification::STATUS_SUPERSEDED ? $latest->superseded_reason : null,
                    'withdrawn_at'     => $latest->consent_withdrawn_at?->toIso8601String(),
                ] : null,
                'id_types'     => collect(config('identity.id_types', []))
                    ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
                'upload'       => [
                    'max_mb' => (int) round(config('identity.upload.max_kb', 5120) / 1024),
                    'mimes'  => config('identity.upload.mimes', []),
                ],
                'consent'      => ConsentNotice::toArray(),
            ],
        ];
    }
}
