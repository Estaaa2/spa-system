<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile API version of the web's EnsureSpaAccess, EnsureBranchOperational
 * and ForcePasswordChange, for routes that use a Sanctum token.
 */
class EnsureMobileAccess
{
    /** Paths a user with a temporary password may still call. */
    private const PASSWORD_CHANGE_ALLOWED = [
        'api/me',
        'api/me/password',
        'api/logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $lock = self::lockFor($user);

        if ($lock !== null) {
            // Revoke this token so the app must log in again, where the same
            // check explains why.
            $token = $user->currentAccessToken();
            if ($token && method_exists($token, 'delete')) {
                $token->delete();
            }

            return response()->json(self::lockedPayload($lock), 423);
        }

        if ($user->password_reset_required && ! $request->is(...self::PASSWORD_CHANGE_ALLOWED)) {
            return response()->json([
                'message'                 => 'Please change your temporary password before continuing.',
                'password_reset_required' => true,
            ], 403);
        }

        return $next($request);
    }

    /**
     * Why this user's spa or branch is locked for the mobile app, or null.
     * Only therapists are checked: customers are not tied to a branch.
     *
     * @return array{reason: string, message: string}|null
     */
    public static function lockFor(User $user): ?array
    {
        if (! $user->hasRole('therapist') || ! $user->spa_id) {
            return null;
        }

        $spa     = $user->spa;
        $contact = ' Please contact your spa owner or manager.';

        if (! $spa || $spa->verification_status !== 'verified') {
            return [
                'reason'  => 'spa_unverified',
                'message' => ($spa?->name ?? 'Your spa') . ' is not yet approved to operate.' . $contact,
            ];
        }

        // Same check the web's EnsureSpaAccess uses (trial or subscription ended).
        if ($spa->isLocked()) {
            return [
                'reason'  => 'subscription',
                'message' => $spa->name . ' is temporarily unavailable because its subscription has ended.' . $contact,
            ];
        }

        $branch = $user->branch_id ? Branch::find($user->branch_id) : null;

        if (! $branch) {
            return null;
        }

        $branch->setRelation('spa', $spa);

        $reason = $branch->lockReason();

        if ($reason === null) {
            return null;
        }

        // Same reasons as the web's EnsureBranchOperational, worded for staff.
        $message = match ($reason) {
            'documents_expired' => $branch->name . ' is locked because its Business Permit has expired.',
            'plan_limit'        => $branch->name . " is not covered by the spa's current plan.",
            default             => $branch->name . ' is locked until an administrator approves its documents.',
        };

        return ['reason' => $reason, 'message' => $message . $contact];
    }

    public static function lockedPayload(array $lock): array
    {
        return [
            'message'       => $lock['message'],
            'branch_locked' => true,
            'reason'        => $lock['reason'],
        ];
    }
}
