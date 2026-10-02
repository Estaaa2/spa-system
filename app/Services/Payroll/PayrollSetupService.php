<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Branch;
use App\Models\CommissionRule;
use App\Models\PayrollRun;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\StaffPayProfile;
use App\Models\StaffRecurringItem;
use App\Models\User;
use App\Services\Payroll\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Payroll setup (Unit 5) — the write paths behind the setup pages, kept out of the
 * controllers so the "used by a finalized run" rules live in one tested place.
 *
 * "Used by a run" is read from payslips.snapshot, which Unit 4 writes
 * (PayrollRunService::regularSnapshot): `pay_profiles[].id`,
 * `rule_ids.commission_rules[]`, `rule_ids.recurring_items[]`. A record is LOCKED when
 * a finalized or released run lists it; `lockedThrough` is the latest period_end of
 * those runs. Locked records are never edited or deleted; their range may only be
 * ended on or after `lockedThrough`, so every date a finalized run paid stays inside
 * the range it was paid under (locked model: "never edit a range already used by a
 * finalized run"; corrections go in as manual adjustment lines in a later run).
 *
 * Usage by draft/approved runs does not lock (those runs are regenerated), but it is
 * reported so the page can tell the user to regenerate.
 */
final class PayrollSetupService
{
    public const LOCKING_STATUSES = [PayrollRun::STATUS_FINALIZED, PayrollRun::STATUS_RELEASED];
    public const PENDING_STATUSES = [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_APPROVED];

    /** Recurring component codes by kind (locked model table 2; config `components`). */
    public const RECURRING_CODES = [
        StaffRecurringItem::KIND_EARNING   => ['ALLOWANCE'],
        StaffRecurringItem::KIND_DEDUCTION => PayrollRunService::RECURRING_DEDUCTION_CODES,
    ];

    public function __construct(private readonly StatutoryCalculator $calc)
    {
    }

    // =====================================================================
    // Usage (read)
    // =====================================================================

    /**
     * @return array{profiles: array<int, array{locked_through: ?string, pending: int}>,
     *               recurring: array<int, array{locked_through: ?string, pending: int}>}
     */
    public function staffUsage(Staff $staff): array
    {
        $rows = DB::table('payslips')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.payroll_run_id')
            ->where('payslips.staff_id', $staff->id)
            ->where('payroll_runs.run_type', PayrollRun::TYPE_REGULAR)
            ->get([
                'payroll_runs.status',
                'payroll_runs.period_end',
                DB::raw("json_extract(payslips.snapshot, '$.pay_profiles') as profiles_json"),
                DB::raw("json_extract(payslips.snapshot, '$.rule_ids.recurring_items') as recurring_json"),
            ]);

        $profiles = [];
        $recurring = [];
        foreach ($rows as $r) {
            $profileIds = array_map(
                static fn ($p) => is_array($p) ? (int) ($p['id'] ?? 0) : 0,
                $this->decodeList($r->profiles_json)
            );
            $this->tally($profiles, $profileIds, (string) $r->status, (string) $r->period_end);
            $this->tally($recurring, array_map('intval', $this->decodeList($r->recurring_json)), (string) $r->status, (string) $r->period_end);
        }

        return ['profiles' => $profiles, 'recurring' => $recurring];
    }

    /** @return array<int, array{locked_through: ?string, pending: int}> keyed by commission_rules.id */
    public function commissionRuleUsage(Spa $spa): array
    {
        $usage = [];

        DB::table('payslips')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.payroll_run_id')
            ->where('payroll_runs.spa_id', $spa->id)
            ->where('payroll_runs.run_type', PayrollRun::TYPE_REGULAR)
            ->select([
                'payslips.id',
                'payroll_runs.status',
                'payroll_runs.period_end',
                DB::raw("json_extract(payslips.snapshot, '$.rule_ids.commission_rules') as rules_json"),
            ])
            ->orderBy('payslips.id')
            ->chunk(500, function ($rows) use (&$usage) {
                foreach ($rows as $r) {
                    $this->tally($usage, array_map('intval', $this->decodeList($r->rules_json)), (string) $r->status, (string) $r->period_end);
                }
            });

        return $usage;
    }

    /** Latest period_end of a finalized/released regular run that has a payslip for this staff member. */
    public function lastFinalizedPeriodEnd(Staff $staff): ?string
    {
        $end = DB::table('payslips')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.payroll_run_id')
            ->where('payslips.staff_id', $staff->id)
            ->where('payroll_runs.run_type', PayrollRun::TYPE_REGULAR)
            ->whereIn('payroll_runs.status', self::LOCKING_STATUSES)
            ->max('payroll_runs.period_end');

        return $end ? substr((string) $end, 0, 10) : null;
    }

    // =====================================================================
    // Spa schedule and branch minimum wage
    // =====================================================================

    /** @param array{payroll_first_cutoff_day: int, payroll_pay_day_offset: int} $data */
    public function updateSchedule(Spa $spa, array $data): void
    {
        $spa->forceFill([
            'payroll_first_cutoff_day' => (int) $data['payroll_first_cutoff_day'],
            'payroll_pay_day_offset'   => (int) $data['payroll_pay_day_offset'],
        ])->save();
    }

    /** @param array{min_daily_wage: string, wage_order_ref: string, min_wage_effective_from: string} $data */
    public function updateBranchWage(Branch $branch, array $data): void
    {
        $branch->forceFill([
            'min_daily_wage'          => $data['min_daily_wage'],
            'wage_order_ref'          => $data['wage_order_ref'],
            'min_wage_effective_from' => $data['min_wage_effective_from'],
        ])->save();
    }

    // =====================================================================
    // Commission rules
    // =====================================================================

    public function createRule(Spa $spa, array $data, User $by): CommissionRule
    {
        return DB::transaction(fn () => CommissionRule::create($this->ruleAttributes($spa, $data) + ['created_by' => $by->id]));
    }

    /** Full edit — only for a rule no finalized run has used. */
    public function updateRule(CommissionRule $rule, array $data): CommissionRule
    {
        return DB::transaction(function () use ($rule, $data) {
            $this->assertRuleUnlocked($rule, 'edited');
            $rule->fill($this->ruleAttributes($rule->spa, $data))->save();

            return $rule;
        });
    }

    /**
     * "Change from date": end the rule the day before $from and start a new rule for the
     * same branch and target with the new method/value (locked model: changing a rule
     * creates a new effective range).
     */
    public function supersedeRule(CommissionRule $rule, string $method, string $value, string $from, ?string $to, User $by): CommissionRule
    {
        return DB::transaction(function () use ($rule, $method, $value, $from, $to, $by) {
            $rule = CommissionRule::query()->lockForUpdate()->findOrFail($rule->id);

            if ($from <= $rule->effective_from->toDateString()) {
                throw ValidationException::withMessages(['effective_from' =>
                    "The new rate must start after this rule's start date ({$rule->effective_from->toDateString()}). To correct this rule instead, edit it."]);
            }
            if ($rule->effective_to !== null && $from > $rule->effective_to->toDateString()) {
                throw ValidationException::withMessages(['effective_from' =>
                    "This rule already ends on {$rule->effective_to->toDateString()}. Add a new rule for dates after that instead."]);
            }

            $this->endRuleAt($rule, Carbon::parse($from)->subDay()->toDateString());

            return CommissionRule::create([
                'spa_id'         => $rule->spa_id,
                'branch_id'      => $rule->branch_id,
                'target_type'    => $rule->target_type,
                'target_id'      => $rule->target_id,
                'method'         => $method,
                'value'          => $value,
                'effective_from' => $from,
                'effective_to'   => $to,
                'created_by'     => $by->id,
            ]);
        });
    }

    public function endRule(CommissionRule $rule, string $endDate): void
    {
        DB::transaction(function () use ($rule, $endDate) {
            $this->endRuleAt(CommissionRule::query()->lockForUpdate()->findOrFail($rule->id), $endDate);
        });
    }

    public function deleteRule(CommissionRule $rule): void
    {
        DB::transaction(function () use ($rule) {
            $this->assertRuleUnlocked($rule, 'deleted');
            $rule->delete();
        });
    }

    /**
     * Which rule applies — the same resolver the run engine uses (CommissionEarnings →
     * CommissionRule::resolveFor), so the preview cannot drift from payroll.
     *
     * @return array{rule: ?CommissionRule, tier: ?string}
     */
    public function previewRule(Spa $spa, ?int $branchId, string $targetType, ?int $targetId, string $date): array
    {
        $rule = CommissionRule::resolveFor((int) $spa->id, $branchId, $targetType, $targetId, $date);

        return ['rule' => $rule, 'tier' => $rule ? self::tierLabel($rule) : null];
    }

    public static function tierLabel(CommissionRule $rule): string
    {
        $isDefault = $rule->target_type === CommissionRule::TARGET_DEFAULT;

        return match (true) {
            ! $isDefault && $rule->branch_id !== null => 'branch_target',
            ! $isDefault                              => 'spa_target',
            $rule->branch_id !== null                 => 'branch_default',
            default                                   => 'spa_default',
        };
    }

    // =====================================================================
    // Pay profiles
    // =====================================================================

    /**
     * "New profile from date". If the profile in effect the day before $from is still
     * open (or runs past $from) it is ended the day before — allowed only when that
     * does not cut into dates a finalized run paid under it.
     *
     * @return array{profile: StaffPayProfile, closed: ?StaffPayProfile}
     */
    public function createProfile(Staff $staff, array $data, User $by): array
    {
        return DB::transaction(function () use ($staff, $data, $by) {
            $from = $data['effective_from'];
            $closed = null;

            $previous = StaffPayProfile::query()
                ->where('staff_id', $staff->id)
                ->where('effective_from', '<', $from)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
                ->lockForUpdate()
                ->first();

            if ($previous !== null) {
                $end = Carbon::parse($from)->subDay()->toDateString();
                $this->assertCanEnd($this->staffUsage($staff)['profiles'][$previous->id]['locked_through'] ?? null, $end,
                    'effective_from', "Pay profile starting {$previous->effective_from->toDateString()}");
                $previous->effective_to = $end;
                $previous->save();
                $closed = $previous;
            }

            $profile = StaffPayProfile::create($this->profileAttributes($data) + [
                'staff_id'       => $staff->id,
                'effective_from' => $from,
                'effective_to'   => $data['effective_to'] ?? null,
                'created_by'     => $by->id,
            ]);

            return ['profile' => $profile, 'closed' => $closed];
        });
    }

    /** Full edit — only for a profile no finalized run has used. */
    public function updateProfile(StaffPayProfile $profile, array $data): StaffPayProfile
    {
        return DB::transaction(function () use ($profile, $data) {
            $this->assertProfileUnlocked($profile, 'edited');
            $profile->fill($this->profileAttributes($data) + [
                'effective_from' => $data['effective_from'],
                'effective_to'   => $data['effective_to'] ?? null,
            ])->save();

            return $profile;
        });
    }

    public function deleteProfile(StaffPayProfile $profile): void
    {
        DB::transaction(function () use ($profile) {
            $this->assertProfileUnlocked($profile, 'deleted');
            $profile->delete();
        });
    }

    /**
     * The profile's daily rate and whether it is below the home-branch minimum wage.
     * Same derivation as RateResolver::dailyRate (monthly: base × 12 ÷ 365, rates
     * sheet §10 — factor VERIFY).
     *
     * @return array{daily: ?string, min: ?string, below: bool}
     */
    public function minimumWageCheck(StaffPayProfile $profile, ?Branch $home): array
    {
        $min = $home?->min_daily_wage === null ? null : Decimal::round2(Decimal::fromDb($home->min_daily_wage));
        $date = $profile->effective_from->toDateString();

        try {
            $base = Decimal::round2(Decimal::fromDb($profile->base_rate));
            $daily = $profile->pay_basis === StaffPayProfile::BASIS_MONTHLY
                ? $this->calc->dailyRateFromMonthly($base, $this->calc->eemrFactor('monthly', count($profile->rest_days ?? []), $date))
                : $base;
        } catch (InvalidArgumentException) {
            // No factor for this date / shape — the run engine reports it; show nothing here.
            return ['daily' => null, 'min' => $min, 'below' => false];
        }

        return ['daily' => $daily, 'min' => $min, 'below' => $min !== null && Decimal::cmp($daily, $min) < 0];
    }

    // =====================================================================
    // Statutory IDs
    // =====================================================================

    /** @param array<string, ?string> $ids tin, sss_no, philhealth_no, pagibig_no */
    public function updateStatutoryIds(Staff $staff, array $ids): void
    {
        foreach (['tin', 'sss_no', 'philhealth_no', 'pagibig_no'] as $attr) {
            if (array_key_exists($attr, $ids)) {
                $v = $ids[$attr] === null ? null : trim((string) $ids[$attr]);
                $staff->{$attr} = $v === '' ? null : $v;
            }
        }
        $staff->save();
    }

    // =====================================================================
    // Recurring items
    // =====================================================================

    public function createRecurring(Staff $staff, array $data, User $by): StaffRecurringItem
    {
        return DB::transaction(fn () => StaffRecurringItem::create([
            'staff_id'          => $staff->id,
            'kind'              => $data['kind'],
            'component_code'    => $data['component_code'],
            'label'             => $data['label'],
            'amount'            => $data['amount'],
            'frequency'         => $data['frequency'],
            'start_date'        => $data['start_date'],
            'end_date'          => $data['end_date'] ?? null,
            'authorization_ref' => $data['kind'] === StaffRecurringItem::KIND_DEDUCTION ? $data['authorization_ref'] : null,
            'is_active'         => true,
            'created_by'        => $by->id,
        ]));
    }

    /** Stop = set the end date. The last day paid/deducted under a finalized run cannot be cut. */
    public function stopRecurring(StaffRecurringItem $item, string $endDate): void
    {
        DB::transaction(function () use ($item, $endDate) {
            $item = StaffRecurringItem::query()->lockForUpdate()->findOrFail($item->id);

            if ($item->end_date !== null && $item->end_date->toDateString() <= $endDate) {
                throw ValidationException::withMessages(['end_date' =>
                    "This item already ends on {$item->end_date->toDateString()}."]);
            }

            $locked = $this->staffUsage($item->staff)['recurring'][$item->id]['locked_through'] ?? null;
            $this->assertCanEnd($locked, $endDate, 'end_date', 'This item');

            $item->end_date = $endDate;
            $item->save();
        });
    }

    public function deleteRecurring(StaffRecurringItem $item): void
    {
        DB::transaction(function () use ($item) {
            if (($this->staffUsage($item->staff)['recurring'][$item->id]['locked_through'] ?? null) !== null) {
                throw ValidationException::withMessages(['recurring' =>
                    'This item was paid or deducted in a finalized payroll run and cannot be deleted. Stop it instead.']);
            }
            $item->delete();
        });
    }

    // =====================================================================
    // Internals
    // =====================================================================

    private function endRuleAt(CommissionRule $rule, string $end): void
    {
        if ($end < $rule->effective_from->toDateString()) {
            throw ValidationException::withMessages(['effective_to' =>
                "The end date cannot be before the rule's start date ({$rule->effective_from->toDateString()})."]);
        }
        if ($rule->effective_to !== null && $rule->effective_to->toDateString() <= $end) {
            throw ValidationException::withMessages(['effective_to' =>
                "This rule already ends on {$rule->effective_to->toDateString()}."]);
        }

        $locked = $this->commissionRuleUsage($rule->spa)[$rule->id]['locked_through'] ?? null;
        $this->assertCanEnd($locked, $end, 'effective_to', "Rule #{$rule->id}");

        $rule->effective_to = $end;
        $rule->save();
    }

    private function assertCanEnd(?string $lockedThrough, string $end, string $field, string $what): void
    {
        if ($lockedThrough !== null && $end < $lockedThrough) {
            throw ValidationException::withMessages([$field => sprintf(
                '%s was used by a finalized payroll run covering up to %s, so its range cannot end before that date. Pick %s or later; correct past pay with an adjustment line in a later run.',
                $what, $lockedThrough, $lockedThrough,
            )]);
        }
    }

    private function assertRuleUnlocked(CommissionRule $rule, string $verb): void
    {
        $locked = $this->commissionRuleUsage($rule->spa)[$rule->id]['locked_through'] ?? null;
        if ($locked !== null) {
            throw ValidationException::withMessages(['rule' =>
                "Rule #{$rule->id} was used by a finalized payroll run (through {$locked}) and cannot be {$verb}. Use “Change from date” or “End” instead."]);
        }
    }

    private function assertProfileUnlocked(StaffPayProfile $profile, string $verb): void
    {
        $locked = $this->staffUsage($profile->staff)['profiles'][$profile->id]['locked_through'] ?? null;
        if ($locked !== null) {
            throw ValidationException::withMessages(['profile' =>
                "This pay profile was used by a finalized payroll run (through {$locked}) and cannot be {$verb}. Create a new profile from a later date instead."]);
        }
    }

    /** @return array<string, mixed> */
    private function ruleAttributes(Spa $spa, array $data): array
    {
        $isDefault = $data['target_type'] === CommissionRule::TARGET_DEFAULT;

        return [
            'spa_id'         => $spa->id,
            'branch_id'      => $data['branch_id'] ?? null,
            'target_type'    => $data['target_type'],
            'target_id'      => $isDefault ? null : (int) $data['target_id'],
            'method'         => $data['method'],
            'value'          => $data['value'],
            'effective_from' => $data['effective_from'],
            'effective_to'   => $data['effective_to'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function profileAttributes(array $data): array
    {
        $restDays = array_values(array_intersect(StaffPayProfile::DAY_NAMES, $data['rest_days'] ?? []));

        return [
            'pay_basis'          => $data['pay_basis'],
            'base_rate'          => $data['base_rate'],
            'commission_enabled' => (bool) ($data['commission_enabled'] ?? false),
            'rest_days'          => $restDays,
        ];
    }

    /**
     * @param array<int, array{locked_through: ?string, pending: int}> $into
     * @param list<int> $ids
     */
    private function tally(array &$into, array $ids, string $status, string $periodEnd): void
    {
        $periodEnd = substr($periodEnd, 0, 10);

        foreach (array_unique(array_filter($ids)) as $id) {
            $into[$id] ??= ['locked_through' => null, 'pending' => 0];

            if (in_array($status, self::LOCKING_STATUSES, true)) {
                $cur = $into[$id]['locked_through'];
                $into[$id]['locked_through'] = $cur === null || $periodEnd > $cur ? $periodEnd : $cur;
            } elseif (in_array($status, self::PENDING_STATUSES, true)) {
                $into[$id]['pending']++;
            }
        }
    }

    /** @return list<mixed> */
    private function decodeList(mixed $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $v = is_string($json) ? json_decode($json, true) : $json;

        return is_array($v) ? array_values($v) : [];
    }
}
