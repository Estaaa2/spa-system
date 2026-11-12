<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Staff;
use App\Models\StaffPayProfile;
use App\Services\Staff\WorkScheduleResolver;
use App\Services\Payroll\Support\Decimal;
use App\Services\Payroll\ValueObjects\PayProfileSnapshot;
use App\Services\Payroll\ValueObjects\ProfileTimeline;
use InvalidArgumentException;

/**
 * Daily and hourly rates from the pay profile effective on a date.
 *
 *  - Daily-paid:   daily = base_rate.
 *  - Monthly-paid: daily = base_rate × 12 ÷ 365 (data model v3.1 DERIVED; rates sheet
 *                  §10 — factor VERIFY). The factor comes from
 *                  StatutoryCalculator::eemrFactor('monthly', …), not from
 *                  spas.monthly_rate_divisor, which v3.1 deprecated ("never read").
 *  - Hourly:       daily ÷ normal_daily_hours (8 — §6).
 *
 * Rounding: daily to the centavo (same as dailyRateFromMonthly), hourly to 4 dp
 * (payslip_lines.rate scale). No source states a rule — UNVERIFIED.
 */
final class RateResolver
{
    public function __construct(
        private readonly StatutoryCalculator $calc,
        private readonly ?WorkScheduleResolver $schedules = null,
    ) {
    }

    public function dailyRate(PayProfileSnapshot $p, string $date): string
    {
        return match ($p->payBasis) {
            PayProfileSnapshot::DAILY   => Decimal::round2($p->baseRate),
            PayProfileSnapshot::MONTHLY => $this->calc->dailyRateFromMonthly(
                Decimal::round2($p->baseRate),
                $this->calc->eemrFactor('monthly', count($p->restDays), $date),
            ),
            default => throw new InvalidArgumentException("Unknown pay basis [{$p->payBasis}] on pay profile #{$p->id}."),
        };
    }

    public function hourlyRate(PayProfileSnapshot $p, string $date): string
    {
        return Decimal::round4(Decimal::div($this->dailyRate($p, $date), $this->calc->normalDailyHours($date)));
    }

    /** Every profile of the staff member that starts on or before $upTo (commission needs old ones too). */
    public function timelineFor(Staff $staff, string $upTo): ProfileTimeline
    {
        $profiles = StaffPayProfile::query()
            ->where('staff_id', $staff->id)
            ->where('effective_from', '<=', $upTo)
            ->orderBy('effective_from')
            ->get()
            ->map(fn (StaffPayProfile $p) => PayProfileSnapshot::fromModel($p))
            ->all();

        // Staff scheduling Phase 4 (D3 B): when a work schedule is in effect at the
        // end of a profile's stretch, its weekly rest days replace the profile's.
        // That count sets the daily-paid DOLE factor (1 → 313, 2 → 261) and is what
        // the payslip snapshot stores. Day-by-day rest days (swaps, mid-cutoff
        // pattern changes) come from CutoffContext::isRestDay(). No schedule → the
        // pay profile's own rest days, exactly as before.
        $schedules = $this->schedules ?? app(WorkScheduleResolver::class);
        $profiles = array_map(function (PayProfileSnapshot $p) use ($staff, $upTo, $schedules) {
            $at = $p->effectiveTo !== null && $p->effectiveTo < $upTo ? $p->effectiveTo : $upTo;
            $rest = $schedules->patternRestDays($staff, $at);

            return $rest === null ? $p : $p->withRestDays($rest);
        }, $profiles);

        return new ProfileTimeline($profiles);
    }
}
