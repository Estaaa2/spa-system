<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\IdentityVerificationResult;
use App\Models\IdentityVerification;
use App\Models\Spa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Services\Identity\IdentityVerificationService;

/**
 * Platform-admin review of staff identity verifications (Phase 3, D4).
 *
 * GET  /admin/identity-verifications                         list (tabs, search, spa filter)
 * GET  /admin/identity-verifications/{verification}          review page
 * POST /admin/identity-verifications/{verification}/approve  approve (files deleted)
 * POST /admin/identity-verifications/{verification}/reject   reject with reason (files deleted)
 *
 * Images are streamed by IdentityVerificationFileController (every view logged).
 * All state changes go through IdentityVerificationService. The staff member is
 * emailed the result (Q13); a mail failure never undoes the decision.
 */
class IdentityVerificationController extends Controller
{
    /** Tab => statuses it lists. "Closed" = withdrawn or superseded, kept for the audit trail. */
    public const TABS = [
        'pending'  => [IdentityVerification::STATUS_PENDING],
        'approved' => [IdentityVerification::STATUS_APPROVED],
        'rejected' => [IdentityVerification::STATUS_REJECTED],
        'closed'   => [IdentityVerification::STATUS_WITHDRAWN, IdentityVerification::STATUS_SUPERSEDED],
    ];

    /** Common reasons the reviewer can insert with one tap, then edit. */
    public const QUICK_REASONS = [
        'The ID photo is blurry or cut off, so the name or photo on it can\'t be read.',
        'The person in the selfie does not clearly match your profile photo.',
        'The name on the ID does not match the name on your account.',
        'The ID in the selfie is not the same ID as in the ID photo.',
        'This ID is expired, or it is not one of the accepted IDs.',
        'Your profile photo does not clearly show your face.',
    ];

    public function __construct(private IdentityVerificationService $identity)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('review-identity-verifications');

        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'pending';
        $q = trim((string) $request->query('q', ''));
        $spaId = $request->integer('spa') ?: null;

        $base = IdentityVerification::query()
            ->when($spaId, fn ($query) => $query->where('spa_id', $spaId))
            ->when($q !== '', function ($query) use ($q) {
                $search = '%'.addcslashes($q, '%_').'%';

                $query->where(function ($match) use ($search) {
                    $match->whereHas('user', fn ($u) => $u->withTrashed()->where(fn ($w) => $w
                            ->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('email', 'like', $search)))
                        ->orWhereHas('spa', fn ($s) => $s->where('name', 'like', $search));
                });
            });

        $counts = collect(self::TABS)->map(fn ($statuses) => (clone $base)->whereIn('status', $statuses)->count());

        $verifications = (clone $base)
            ->whereIn('status', self::TABS[$tab])
            ->with([
                'user' => fn ($u) => $u->withTrashed()->with('staff.branch'),
                'spa:id,name',
                'reviewer:id,first_name,last_name',
            ])
            // Oldest first while waiting (first come, first served); newest first otherwise.
            ->when($tab === 'pending',
                fn ($query) => $query->orderBy('submitted_at')->orderBy('id'),
                fn ($query) => $query->orderByDesc('reviewed_at')->orderByDesc('submitted_at')->orderByDesc('id'))
            ->paginate(15)
            ->withQueryString();

        $spas = Spa::query()
            ->whereIn('id', IdentityVerification::query()->whereNotNull('spa_id')->select('spa_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.identity-verifications.index', compact('verifications', 'counts', 'tab', 'q', 'spaId', 'spas'));
    }

    public function show(IdentityVerification $verification): View
    {
        Gate::authorize('review-identity-verifications');

        $verification->load([
            'user' => fn ($u) => $u->withTrashed()->with(['staff.branch', 'roles']),
            'spa:id,name',
            'reviewer:id,first_name,last_name',
            'fileViews' => fn ($v) => $v->with('viewer:id,first_name,last_name')->latest('viewed_at')->limit(20),
        ]);

        $disk = $this->identity->disk();
        $files = [];

        foreach ([IdentityVerification::FILE_PHOTO, IdentityVerification::FILE_ID, IdentityVerification::FILE_SELFIE] as $file) {
            $path = $file === IdentityVerification::FILE_PHOTO
                ? $verification->photo_path_reviewed
                : $verification->pathFor($file);

            $files[$file] = $path && $disk->exists($path)
                ? route('admin.identity-verifications.files.show', [$verification, $file])
                : null;
        }

        $history = IdentityVerification::query()
            ->where('user_id', $verification->user_id)
            ->whereKeyNot($verification->id)
            ->with('reviewer:id,first_name,last_name')
            ->latest('submitted_at')
            ->limit(10)
            ->get();

        $user = $verification->user;

        return view('admin.identity-verifications.show', [
            'verification'  => $verification,
            'user'          => $user,
            'files'         => $files,
            'history'       => $history,
            'quickReasons'  => self::QUICK_REASONS,
            'accountClosed' => $user === null || $user->trashed(),
            // The request is stale if the photo or name changed after it was sent;
            // approve() would then close it, so warn the reviewer up front.
            'photoChanged'  => $user && $user->profile_photo_path !== $verification->photo_path_reviewed,
            'nameChanged'   => $user && $user->legalName() !== $verification->name_reviewed,
        ]);
    }

    public function approve(Request $request, IdentityVerification $verification): RedirectResponse
    {
        Gate::authorize('review-identity-verifications');

        $request->validate([
            'checks'   => ['required', 'array', 'size:4'],
            'checks.*' => ['in:same_person,name_matches,same_id,valid_id'],
        ], [
            'checks.required' => 'Tick all four checks before approving.',
            'checks.size'     => 'Tick all four checks before approving.',
        ]);

        if ($verification->user()->withTrashed()->first()?->trashed()) {
            return back()->with('error', 'This account was deleted, so the request can\'t be approved. Reject it to delete the files.');
        }

        try {
            $row = $this->identity->approve($verification, $request->user());
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.identity-verifications.show', $verification)
                ->with('error', collect($e->errors())->flatten()->first());
        }

        $this->notify($row);

        return redirect()
            ->route('admin.identity-verifications.index')
            ->with('success', ($row->user?->name ?? 'The staff member').' is now verified. The ID and selfie were deleted.');
    }

    public function reject(Request $request, IdentityVerification $verification): RedirectResponse
    {
        Gate::authorize('review-identity-verifications');

        $validated = $request->validateWithBag('reject', [
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'rejection_reason.required' => 'Write the reason the staff member will see.',
            'rejection_reason.min'      => 'Write the reason the staff member will see (at least 5 characters).',
        ]);

        try {
            $row = $this->identity->reject($verification, $request->user(), $validated['rejection_reason']);
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.identity-verifications.show', $verification)
                ->with('error', collect($e->errors())->flatten()->first());
        }

        $this->notify($row);

        return redirect()
            ->route('admin.identity-verifications.index')
            ->with('success', 'Request rejected and the staff member was told why. The ID and selfie were deleted.');
    }

    /** Emails the result. A mail problem is logged and never undoes the decision. */
    private function notify(IdentityVerification $row): void
    {
        $row->loadMissing('user');
        $email = $row->user?->email;

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new IdentityVerificationResult($row));
        } catch (\Throwable $e) {
            Log::error('Failed to send identity verification result email.', [
                'verification_id' => $row->id,
                'message'         => $e->getMessage(),
            ]);
        }
    }
}
