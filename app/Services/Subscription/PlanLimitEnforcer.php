<?php

namespace App\Services\Subscription;

use App\Models\Spa;
use App\Models\Staff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a spa's active staff inside its plan's staff limit.
 *
 * Branches need no stored state: Spa::branchIdsWithinPlanLimit() decides
 * which branches run, and Branch::lockReason() locks the rest.
 *
 * Staff are paused by setting employment_status = inactive together with
 * plan_paused_at, so every existing "active staff" query (booking, therapist
 * availability, payroll, listing readiness) leaves them out without being
 * changed. Only rows with plan_paused_at are ever restored, so staff the
 * owner made inactive themselves are never touched.
 *
 * Who stays active, in order:
 *   1. staff in a branch the plan covers;
 *   2. HR and Finance only when the plan includes manpower (Premium);
 *   3. staff the owner picked on the Plan limits page (keep_active);
 *   4. longest-serving first (hire date, then id), so the newest are
 *      paused first, as Shopify does with staff accounts.
 */
class PlanLimitEnforcer
{
    public const MANPOWER_ROLES = ['hr', 'finance', 'hr_head', 'finance_head'];

    /**
     * Applies the plan's staff limit to the spa.
     *
     * @return array{kept: int, paused: int, restored: int, newly_paused: int}
     */
    public function sync(Spa $spa): array
    {
        $result = ['kept' => 0, 'paused' => 0, 'restored' => 0, 'newly_paused' => 0];

        // A locked spa is blocked as a whole already. Its staff keep their
        // state so nothing changes until a plan is active again.
        if (! $spa->hasAccess()) {
            return $result;
        }

        $plan = $this->plan($spa);

        return DB::transaction(function () use ($spa, $plan, $result): array {
            $candidates = $this->candidates($spa, lock: true);

            [$keep, $pause] = $this->partition($candidates, $plan);

            foreach ($keep as $staff) {
                if ($staff->plan_paused_at !== null) {
                    $staff->update(['employment_status' => 'active', 'plan_paused_at' => null]);
                    $result['restored']++;
                }
            }

            foreach ($pause as $staff) {
                if ($staff->plan_paused_at === null) {
                    $staff->update(['employment_status' => 'inactive', 'plan_paused_at' => now()]);
                    $result['newly_paused']++;
                }
            }

            $result['kept'] = $keep->count();
            $result['paused'] = $pause->count();

            return $result;
        });
    }

    /**
     * What sync() would do, without changing anything. Used by the Plan
     * limits page and to warn about bookings before saving.
     *
     * @return array{keep: Collection, pause: Collection, limit: int}
     */
    public function preview(Spa $spa): array
    {
        $plan = $this->plan($spa);

        [$keep, $pause] = $this->partition($this->candidates($spa), $plan);

        return ['keep' => $keep, 'pause' => $pause, 'limit' => $plan['limit']];
    }

    /**
     * Why a staff member cannot be active under the current plan, or null.
     * Values: branch (their branch is over the branch limit),
     * role (HR/Finance without manpower).
     */
    public function blockedReason(Staff $staff, Spa $spa): ?string
    {
        $plan = $this->plan($spa);

        if (! in_array((int) $staff->branch_id, $plan['branch_ids'], true)) {
            return 'branch';
        }

        if (! $plan['manpower'] && in_array($this->role($staff), self::MANPOWER_ROLES, true)) {
            return 'role';
        }

        return null;
    }

    /** @return array{limit: int, branch_ids: array<int, int>, manpower: bool} */
    private function plan(Spa $spa): array
    {
        $spa->forgetSubscriptionCache();

        return [
            'limit' => max(0, $spa->planLimit('staff')),
            'branch_ids' => $spa->branchIdsWithinPlanLimit(),
            'manpower' => $spa->hasFeature('manpower'),
        ];
    }

    /** Active staff plus staff paused by the plan; never hand-made inactive staff. */
    private function candidates(Spa $spa, bool $lock = false): Collection
    {
        $query = Staff::query()
            ->with(['user.roles', 'branch'])
            ->where('spa_id', $spa->id)
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q
                ->where('employment_status', 'active')
                ->orWhereNotNull('plan_paused_at'));

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /** @return array{0: Collection, 1: Collection} [keep, pause] */
    private function partition(Collection $candidates, array $plan): array
    {
        [$eligible, $blocked] = $candidates->partition(function (Staff $staff) use ($plan) {
            if (! in_array((int) $staff->branch_id, $plan['branch_ids'], true)) {
                return false;
            }

            return $plan['manpower']
                || ! in_array($this->role($staff), self::MANPOWER_ROLES, true);
        });

        $ordered = $eligible
            ->sortBy(fn (Staff $staff) => [
                $staff->keep_active ? 0 : 1,
                $staff->hire_date ? strtotime((string) $staff->hire_date) : ($staff->created_at?->getTimestamp() ?? 0),
                $staff->id,
            ])
            ->values();

        $keep = $ordered->take($plan['limit'])->values();
        $pause = $ordered->slice($plan['limit'])->values()->merge($blocked)->values();

        return [$keep, $pause];
    }

    private function role(Staff $staff): ?string
    {
        return $staff->user?->roles->pluck('name')->first();
    }
}
