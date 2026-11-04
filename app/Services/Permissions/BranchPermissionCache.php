<?php

declare(strict_types=1);

namespace App\Services\Permissions;

use App\Models\Branch;
use App\Models\BranchRolePermission;

/**
 * Request-lifetime memo for User::hasBranchPermission() (sidebar & RBAC rework
 * Phase 2, finding F3). Before this, every permission check ran its own
 * branch_role_permissions query, and for the owner and the heads also a branch
 * lookup — 40 to 110 queries per sidebar render.
 *
 * Now each (branch, role) override set is loaded once, the main branch once per
 * spa, and the session-branch check once per (spa, branch).
 *
 * Flushed after every HTTP request (AppServiceProvider, RequestHandled) and
 * whenever a BranchRolePermission or Branch model is saved or deleted, so a
 * change is seen by the next check. Query-builder writes that skip model events
 * (the Roles & Permissions save) end with a redirect, so the next request starts
 * fresh anyway; call flush() after such a write if a later check in the same
 * request must see it.
 */
final class BranchPermissionCache
{
    /** @var array<string, array<string, bool>> "branchId|role" => [permission => granted] */
    private array $overrides = [];

    /** @var array<int, int|null> spaId => main branch id */
    private array $mainBranch = [];

    /** @var array<string, bool> "spaId|branchId" => belongs */
    private array $belongs = [];

    /** @return array<string, bool> */
    public function overrides(int $branchId, ?string $role): array
    {
        if ($role === null) {
            return [];
        }

        $key = $branchId.'|'.$role;

        return $this->overrides[$key] ??= BranchRolePermission::query()
            ->where('branch_id', $branchId)
            ->where('role_name', $role)
            ->pluck('granted', 'permission_name')
            ->map(fn ($granted) => (bool) $granted)
            ->all();
    }

    /** Main branch, or the first branch when none is marked main. */
    public function mainBranchId(int $spaId): ?int
    {
        if (! array_key_exists($spaId, $this->mainBranch)) {
            $id = Branch::query()->where('spa_id', $spaId)->orderByDesc('is_main')->orderBy('id')->value('id');
            $this->mainBranch[$spaId] = $id !== null ? (int) $id : null;
        }

        return $this->mainBranch[$spaId];
    }

    public function branchBelongsToSpa(int $branchId, int $spaId): bool
    {
        return $this->belongs[$spaId.'|'.$branchId]
            ??= Branch::query()->where('id', $branchId)->where('spa_id', $spaId)->exists();
    }

    public function flush(): void
    {
        $this->overrides = [];
        $this->mainBranch = [];
        $this->belongs = [];
    }
}
