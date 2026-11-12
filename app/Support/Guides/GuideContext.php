<?php

namespace App\Support\Guides;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Answers the `when` conditions in Guides for one user (help guides Phase 1).
 * Each answer is the same check the page itself uses to show that button.
 */
final class GuideContext
{
    public const CONDITIONS = ['switcher', 'spa_zone', 'self_service', 'reassign', 'can_verify'];

    public const PREFIXES = ['perm:', 'feature:'];

    /** @var array<string, bool> */
    private array $memo = [];

    public function __construct(
        private readonly User $user,
        private readonly array $nav,
    ) {
    }

    /** @param  string|list<string>|null  $when  all conditions must pass */
    public function passes(string|array|null $when): bool
    {
        foreach ((array) $when as $condition) {
            if (! ($this->memo[$condition] ??= $this->check($condition))) {
                return false;
            }
        }

        return true;
    }

    public static function isKnown(string $condition): bool
    {
        if (in_array($condition, self::CONDITIONS, true)) {
            return true;
        }

        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($condition, $prefix) && strlen($condition) > strlen($prefix)) {
                return true;
            }
        }

        return false;
    }

    private function check(string $condition): bool
    {
        $user = $this->user;

        return match (true) {
            str_starts_with($condition, 'perm:') => $user->hasBranchPermission(substr($condition, 5)),
            str_starts_with($condition, 'feature:') => (bool) ($user->spa?->hasFeature(substr($condition, 8)) ?? false),
            // Same rule as the layout's branch switcher (decision D6).
            $condition === 'switcher' => $user->canSwitchBranches() && ($user->spa?->branches()->count() ?? 0) >= 2,
            $condition === 'spa_zone' => collect($this->nav['zones'] ?? [])->contains('key', 'spa'),
            $condition === 'self_service' => $user->hasAttendanceSelfService(),
            $condition === 'reassign' => $user->canRequestReassignment(),
            $condition === 'can_verify' => Gate::forUser($user)->allows('submit-identity-verification'),
            default => false,
        };
    }
}
