<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Spa;
use App\Models\Staff;
use App\Services\Subscription\PlanLimitEnforcer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Plan limits": the owner chooses which branches and staff stay active
 * when the spa has more than its plan allows. Unpicked slots are filled
 * automatically (main branch, then oldest branches; longest-serving staff).
 */
class PlanLimitController extends Controller
{
    public function __construct(
        private readonly PlanLimitEnforcer $enforcer,
    ) {}

    public function edit(Request $request): View|RedirectResponse
    {
        $spa = $request->user()->spa;

        abort_unless($spa, 403);

        if (! $spa->hasAccess()) {
            return redirect()
                ->route('owner.subscription.index')
                ->with('error', 'Choose a plan first. Plan limits apply while your spa has an active plan.');
        }

        $preview = $this->enforcer->preview($spa);

        $branches = $spa->branches()
            ->whereNull('deleted_at')
            ->withCount(['users'])
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        $staff = $preview['keep']->merge($preview['pause'])
            ->sortBy(fn (Staff $member) => [
                $member->branch?->name ?? '',
                $member->user?->first_name ?? '',
            ])
            ->values();

        $blocked = $staff->mapWithKeys(fn (Staff $member) => [
            $member->id => $this->enforcer->blockedReason($member, $spa),
        ]);

        return view('owner.subscription.limits', [
            'spa' => $spa,
            'planKey' => $spa->currentPlan(),
            'branchLimit' => $spa->planLimit('branches'),
            'staffLimit' => $preview['limit'],
            'activeBranchIds' => $spa->branchIdsWithinPlanLimit(),
            'branches' => $branches,
            'staff' => $staff,
            'keepIds' => $preview['keep']->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'blocked' => $blocked,
            'affectedBookings' => $this->bookingsWithPausedTherapists($spa),
            'canSwap' => $spa->canSwapPlanSelection(),
            'nextSwapAt' => $spa->nextPlanSwapAt(),
            'isOverLimits' => $spa->isOverPlanLimits(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $spa = $request->user()->spa;

        abort_unless($spa, 403);

        // Start from the stored state, never from values cached earlier.
        $spa->forgetSubscriptionCache();

        if (! $spa->hasAccess()) {
            return redirect()->route('owner.subscription.index');
        }

        $validated = $request->validate([
            'branches' => ['nullable', 'array'],
            'branches.*' => ['integer'],
            'staff' => ['nullable', 'array'],
            'staff.*' => ['integer'],
        ]);

        $branchLimit = $spa->planLimit('branches');
        $staffLimit = $spa->planLimit('staff');

        $branchIds = $spa->branches()
            ->whereNull('deleted_at')
            ->whereIn('id', $validated['branches'] ?? [])
            ->where('is_main', false)
            ->pluck('id');

        // The main branch always runs and takes one slot.
        if ($branchIds->count() > max(0, $branchLimit - 1)) {
            throw ValidationException::withMessages([
                'branches' => "Your plan runs {$branchLimit} branches including the main branch. Pick at most " . max(0, $branchLimit - 1) . ' other branch(es).',
            ]);
        }

        $staffIds = Staff::query()
            ->where('spa_id', $spa->id)
            ->whereNull('deleted_at')
            ->whereIn('id', $validated['staff'] ?? [])
            ->pluck('id');

        if ($staffIds->count() > $staffLimit) {
            throw ValidationException::withMessages([
                'staff' => "Your plan allows {$staffLimit} active staff. Pick at most {$staffLimit}.",
            ]);
        }

        // Who runs under the current picks. Taken from the rules, not from
        // the stored flags, so catching up after a plan change (before the
        // hourly sync ran) is never counted as the owner's swap.
        $branchesBefore = $spa->branchIdsWithinPlanLimit();
        $staffBefore = $this->enforcer->preview($spa)['keep']
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Worked out before the picks change anything, for the error message.
        $nextSwapAt = $spa->nextPlanSwapAt();

        // Throwing inside the transaction rolls the picks back.
        DB::transaction(function () use ($spa, $branchIds, $staffIds, $branchesBefore, $staffBefore, $nextSwapAt): void {
            Branch::query()->where('spa_id', $spa->id)->update(['keep_active' => false]);
            Branch::query()->whereIn('id', $branchIds)->update(['keep_active' => true]);

            Staff::query()->where('spa_id', $spa->id)->update(['keep_active' => false]);
            Staff::query()->whereIn('id', $staffIds)->update(['keep_active' => true]);

            // Who would run with the new picks.
            $spa->forgetSubscriptionCache();
            $branchesAfter = $spa->branchIdsWithinPlanLimit();
            $staffAfter = $this->enforcer->preview($spa)['keep']
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            // A swap pauses something that is running now. Filling a free
            // slot, or reordering while under the limit, pauses nothing.
            $isSwap = array_diff($branchesBefore, $branchesAfter) !== []
                || array_diff($staffBefore, $staffAfter) !== [];

            if (! $isSwap) {
                return;
            }

            if (! $spa->canSwapPlanSelection()) {
                throw ValidationException::withMessages([
                    'swap' => 'You have already swapped active branches or staff this billing period.'
                        . ($nextSwapAt ? ' Your next swap is available on ' . $nextSwapAt->format('F d, Y') . '.' : '')
                        . ($spa->currentPlan() === 'basic'
                            ? ' Upgrade to Premium to run more branches and staff now.'
                            : ' Removing a branch or staff member frees a slot right away.'),
                ]);
            }

            $spa->update(['plan_swap_used_at' => now()]);
        });

        $spa->forgetSubscriptionCache();

        $result = $this->enforcer->sync($spa);

        $message = 'Plan limits saved.';

        if ($result['restored'] > 0) {
            $message .= " {$result['restored']} staff member(s) can sign in again.";
        }

        if ($result['newly_paused'] > 0) {
            $message .= " {$result['newly_paused']} staff member(s) were paused.";
        }

        return redirect()
            ->route('owner.subscription.limits')
            ->with('success', $message);
    }

    /**
     * Upcoming bookings assigned to a paused therapist. Nothing is changed
     * automatically; the owner reassigns them.
     */
    private function bookingsWithPausedTherapists(Spa $spa): Collection
    {
        $pausedUserIds = Staff::query()
            ->where('spa_id', $spa->id)
            ->whereNotNull('plan_paused_at')
            ->pluck('user_id');

        if ($pausedUserIds->isEmpty()) {
            return collect();
        }

        return Booking::query()
            ->with(['therapist', 'branch'])
            ->where('spa_id', $spa->id)
            ->whereIn('therapist_id', $pausedUserIds)
            ->whereIn('status', ['reserved', 'pending'])
            ->whereDate('appointment_date', '>=', now()->toDateString())
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->limit(50)
            ->get();
    }
}
