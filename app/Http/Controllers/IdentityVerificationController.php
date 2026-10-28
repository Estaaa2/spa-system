<?php

namespace App\Http\Controllers;

use App\Http\Requests\Identity\SubmitIdentityVerificationRequest;
use App\Services\Identity\IdentityVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Staff side of identity verification (profile page, web).
 *
 * POST   /profile/identity-verification   submit ID + selfie with consent
 * DELETE /profile/identity-verification   withdraw consent (cancels a pending
 *                                          request or ends an approval)
 *
 * The admin review is Phase 3; the mobile API is Phase 6. All rules live in
 * IdentityVerificationService.
 */
class IdentityVerificationController extends Controller
{
    public function __construct(private IdentityVerificationService $identity)
    {
    }

    public function store(SubmitIdentityVerificationRequest $request): RedirectResponse
    {
        try {
            $this->identity->submit(
                $request->user(),
                $request->validated('id_type'),
                $request->file('id_image'),
                $request->file('selfie'),
                'web'
            );
        } catch (ValidationException $e) {
            throw $e->errorBag('identity')->redirectTo(route('profile.edit').'#identity');
        }

        return redirect()->to(route('profile.edit').'#identity')
            ->with('success', 'Submitted for review. The Levictas team will check it, and the result will show here.');
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $row = $this->identity->withdraw($request->user());

        if (! $row) {
            return redirect()->to(route('profile.edit').'#identity')
                ->with('error', 'There is no verification request or approval to withdraw.');
        }

        $message = $row->reviewed_at === null
            ? 'Your request was cancelled and your ID and selfie were deleted.'
            : 'Consent withdrawn. You are no longer verified, so you won\'t be assigned new online home-service bookings.';

        return redirect()->to(route('profile.edit').'#identity')->with('success', $message);
    }
}
