<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Services\Payroll\Support\Decimal;
use App\Services\Payroll\ValueObjects\AttendanceDay;
use App\Services\Payroll\ValueObjects\AttendanceResult;
use App\Services\Payroll\ValueObjects\CutoffContext;
use App\Services\Payroll\ValueObjects\EarningLine;
use App\Services\Payroll\ValueObjects\Holiday;
use App\Services\Payroll\ValueObjects\PayProfileSnapshot;
use App\Services\Payroll\ValueObjects\WorkedDay;
use App\Models\PayslipLine;

/**
 * Attendance-driven earnings for one staff member and one cutoff:
 * BASIC, OT, RESTDAY_PREM, HOLIDAY_PAY, HOLIDAY_PREM, NIGHT_DIFF.
 *
 * Rules (rates sheet §6 unless noted; multipliers from config via StatutoryCalculator):
 *  - Worked day = status present or late (late/undertime deductions are CUT).
 *  - Daily-paid BASIC: one line per worked day at the daily rate.
 *  - Monthly-paid BASIC: ½ of base_rate per cutoff (prorated by calendar days when a
 *    profile covers only part of the cutoff — UNVERIFIED), minus the daily rate for
 *    each absent/on_leave day. Absence rows on rest days or non-working holidays are
 *    ignored: the 365 factor already pays those days (§10). Deductions are capped so
 *    the cutoff's BASIC never goes below 0. Days with no attendance row are NOT
 *    absences (reported as a warning instead).
 *  - Premium days (rest day, special non-working, regular holiday and their rest-day
 *    combinations): premium line = (day multiplier − 1) × daily rate, both pay bases
 *    (the 100% is in BASIC for daily-paid and in the monthly pay for monthly-paid —
 *    UNVERIFIED for monthly-paid). Plain rest day → RESTDAY_PREM; any holiday
 *    combination → HOLIDAY_PREM. Special working days are ordinary days.
 *  - OT hours = (time_out − time_in) − unpaid meal minutes − normal hours, floor 0
 *    (§6 Rev. 3), paid at hourly × the day's _ot multiplier.
 *  - NIGHT_DIFF: minutes worked in 22:00–24:00 and 00:00–06:00 of the same date, at
 *    10% of that day's applicable hourly rate — the day rate for regular hours, the
 *    OT rate for the OT portion (the last OT minutes of the shift). Premium-day
 *    reading is UNVERIFIED (§6). No cross-midnight shifts (known limitation).
 *  - HOLIDAY_PAY: unworked regular holiday, daily-paid only, if present (present/late)
 *    on the workday immediately preceding (Omnibus Rules Book III Rule IV Sec. 6;
 *    §6). Looking back skips the employee's rest days, non-working holidays, and days
 *    the home branch is closed (Sec. 6(c)). Working on a skipped day counts as being
 *    present: for successive regular holidays this is Sec. 10 (work the first →
 *    paid the second); for a worked rest/closed day it is a DESIGN choice.
 *    on_leave does NOT qualify: leave is unpaid in v3 ("leave of absence without
 *    pay" — Sec. 6(a)). Monthly-paid get no HOLIDAY_PAY line: the 365 factor already
 *    pays every day (§10; Omnibus Rules Rule IV "status of employees paid by the month").
 *  - Every line's branch_id is the attendance row's branch (deployments), except
 *    lines with no attendance row (monthly BASIC, HOLIDAY_PAY) → home branch.
 */
final class AttendanceEarnings
{
    /** How far back the preceding-workday search looks. Not statutory; covers 2 rest days + holiday runs. */
    public const LOOKBACK_DAYS = 14;

    public function __construct(
        private readonly StatutoryCalculator $calc,
        private readonly RateResolver $rates,
    ) {
    }

    public function forPeriod(Staff $staff, CutoffContext $ctx): AttendanceResult
    {
        $from = (new \DateTimeImmutable($ctx->periodStart))->modify('-'.self::LOOKBACK_DAYS.' days')->format('Y-m-d');

        // staff_attendance has a deleted_at column but the model has no SoftDeletes,
        // so the soft-delete filter is explicit: a deleted row must never be paid.
        $rows = StaffAttendance::query()
            ->where('staff_id', $staff->id)
            ->whereNull('deleted_at')
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $ctx->periodEnd)
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(fn (StaffAttendance $a) => AttendanceDay::fromModel($a))
            ->all();

        return $this->compute($rows, $ctx);
    }

    /**
     * Pure computation (no DB). $rows may include days before periodStart (lookback).
     *
     * @param list<AttendanceDay> $rows
     */
    public function compute(array $rows, CutoffContext $ctx): AttendanceResult
    {
        $lines = [];
        $worked = [];
        $warnings = [];

        $byDate = [];
        foreach ($rows as $row) {
            if (isset($byDate[$row->date])) {
                $warnings[] = "{$row->date}: more than one attendance row (#{$byDate[$row->date]->id}, #{$row->id}); only #{$byDate[$row->date]->id} was used.";
                continue;
            }
            $byDate[$row->date] = $row;
        }

        // ── Worked days ───────────────────────────────────────────────────
        foreach ($ctx->dates() as $date) {
            $row = $byDate[$date] ?? null;
            if ($row === null || ! $row->isWorked()) {
                continue;
            }

            $profile = $ctx->timeline->on($date);
            if ($profile === null) {
                $warnings[] = "{$date}: attendance recorded (#{$row->id}) but no pay profile is in effect — not paid.";
                continue;
            }

            array_push($lines, ...$this->workedDayLines($row, $profile, $ctx, $warnings));
            $worked[] = new WorkedDay($date, $row->id, $row->branchId, $profile, $this->rates->dailyRate($profile, $date));
        }

        // ── Unworked regular holidays (daily-paid) ────────────────────────
        foreach ($ctx->holidays as $date => $holiday) {
            if (! $ctx->contains($date) || $holiday->type !== Holiday::REGULAR) {
                continue;
            }
            $line = $this->unworkedHolidayLine($date, $holiday, $byDate, $ctx, $warnings);
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        // ── Monthly-paid BASIC and absences ───────────────────────────────
        foreach ($ctx->timeline->overlapping($ctx->periodStart, $ctx->periodEnd) as $profile) {
            if ($profile->payBasis === PayProfileSnapshot::MONTHLY) {
                array_push($lines, ...$this->monthlyBasicLines($profile, $byDate, $ctx, $warnings));
            }
        }

        // Lines with no date (semi-monthly basic, unworked-holiday lines carry their date) sort first.
        usort($lines, static fn (EarningLine $a, EarningLine $b): int => [$a->date ?? '', $a->sourceId ?? 0] <=> [$b->date ?? '', $b->sourceId ?? 0]);

        return new AttendanceResult($lines, $worked, $warnings);
    }

    /**
     * @param list<string> $warnings
     * @return list<EarningLine>
     */
    private function workedDayLines(AttendanceDay $row, PayProfileSnapshot $p, CutoffContext $ctx, array &$warnings): array
    {
        $date = $row->date;
        $daily = $this->rates->dailyRate($p, $date);
        $hourly = $this->rates->hourlyRate($p, $date);
        [$dayKey, $otKey, $premCode, $dayLabel] = $this->classify($date, $p, $ctx);
        $src = PayslipLine::SOURCE_ATTENDANCE;
        $out = [];

        if ($p->isDaily()) {
            $out[] = EarningLine::priced($this->calc->component('BASIC'), '1', $daily, $row->branchId, $src, $row->id,
                "Daily rate ₱".Decimal::peso($daily), $date, $this->shortDate($date));
        }

        $dayMult = $dayKey === null ? '1.00' : $this->calc->multiplier($dayKey, $date);
        if ($dayKey !== null) {
            $premRate = Decimal::mul($daily, Decimal::sub($dayMult, '1'));
            $out[] = EarningLine::priced($this->calc->component($premCode), '1', $premRate, $row->branchId, $src, $row->id,
                sprintf('%s: %s%% of daily rate ₱%s; the first 100%% is in basic pay', $dayLabel, bcmul($dayMult, '100', 0), Decimal::peso($daily)),
                $date, $this->shortDate($date));
        }

        // ── Clocked hours: OT and night differential ──────────────────────
        if ($row->timeIn === null || $row->timeOut === null) {
            return $out;
        }
        if ($row->autoClosed) {
            $warnings[] = "{$date}: attendance #{$row->id} was auto-closed, so its clock-out is not real — OT and night differential were not computed. Correct the times in Attendance if the staff member worked late.";

            return $out;
        }

        $in = $this->minutes($row->timeIn);
        $outMin = $this->minutes($row->timeOut);
        if ($outMin <= $in) {
            $warnings[] = "{$date}: attendance #{$row->id} clock-out ({$row->timeOut}) is not after clock-in ({$row->timeIn}); cross-midnight shifts are not supported — OT and night differential were not computed.";

            return $out;
        }

        $normalMin = (int) bcmul($this->calc->normalDailyHours($date), '60', 0);
        $otMin = max(0, ($outMin - $in) - $this->calc->unpaidMealMinutes($date) - $normalMin);
        $otRate = Decimal::mul($hourly, $this->calc->multiplier($otKey, $date));

        if ($otMin > 0) {
            $out[] = EarningLine::priced($this->calc->component('OT'), Decimal::div((string) $otMin, '60'), $otRate, $row->branchId, $src, $row->id,
                sprintf('%s → %s, less %d-min meal and %s h: %s OT min at ₱%s/h (%s × %s)',
                    substr($row->timeIn, 0, 5), substr($row->timeOut, 0, 5), $this->calc->unpaidMealMinutes($date),
                    rtrim(rtrim($this->calc->normalDailyHours($date), '0'), '.'), $otMin,
                    Decimal::round4($otRate), Decimal::round4($hourly), $this->calc->multiplier($otKey, $date)),
                $date, $this->shortDate($date));
        }

        // Night windows on the same date: 00:00–06:00 and 22:00–24:00 (Art. 86).
        $nightTotal = $this->overlap($in, $outMin, 0, 360) + $this->overlap($in, $outMin, 1320, 1440);
        if ($nightTotal > 0) {
            $otStart = $outMin - $otMin; // OT = the last $otMin minutes of the shift
            $nightOt = $otMin > 0 ? $this->overlap($otStart, $outMin, 0, 360) + $this->overlap($otStart, $outMin, 1320, 1440) : 0;
            $nightReg = $nightTotal - $nightOt;

            $nd = $this->calc->multiplier('night_diff', $date);
            $regBase = Decimal::mul($hourly, $dayMult);
            $amount = Decimal::add(
                Decimal::mul(Decimal::div((string) $nightReg, '60'), Decimal::mul($regBase, $nd)),
                Decimal::mul(Decimal::div((string) $nightOt, '60'), Decimal::mul($otRate, $nd)),
            );
            $hours = Decimal::div((string) $nightTotal, '60');

            $note = sprintf('%d night min: %d at 10%% of ₱%s', $nightTotal, $nightReg, Decimal::round4($regBase));
            if ($nightOt > 0) {
                $note .= sprintf(' + %d (OT) at 10%% of ₱%s', $nightOt, Decimal::round4($otRate));
            }

            // One line per attendance row (payslip_lines unique index); rate = blended per hour.
            $out[] = EarningLine::withAmount($this->calc->component('NIGHT_DIFF'), $hours, Decimal::div($amount, $hours), $amount,
                $row->branchId, $src, $row->id, $note, $date, $this->shortDate($date));
        }

        return $out;
    }

    /**
     * @param array<string, AttendanceDay> $byDate
     * @param list<string> $warnings
     */
    private function unworkedHolidayLine(string $date, Holiday $holiday, array $byDate, CutoffContext $ctx, array &$warnings): ?EarningLine
    {
        $profile = $ctx->timeline->on($date);
        if ($profile === null || ! $profile->isDaily()) {
            return null; // monthly-paid: already in the monthly pay (§6 — UNVERIFIED default)
        }

        $row = $byDate[$date] ?? null;
        if ($row !== null && $row->isWorked()) {
            return null; // worked → BASIC + HOLIDAY_PREM instead
        }

        $check = $this->precedingWorkdayCheck($date, $profile, $byDate, $ctx);
        if ($check === null) {
            $warnings[] = "{$date} {$holiday->name}: no workday found in the ".self::LOOKBACK_DAYS." days before — holiday pay not computed; add it as an adjustment if due.";

            return null;
        }

        [$prev, $present, $reason] = $check;
        if (! $present) {
            $status = ($byDate[$prev] ?? null)?->status ?? 'no attendance record';
            $hint = $status === 'on_leave' ? ' If that leave was paid, add the holiday pay as an adjustment (paid leave qualifies — Omnibus Rules Rule IV Sec. 6(a)).' : '';
            $warnings[] = "{$date} {$holiday->name}: not paid — not present on the preceding workday {$prev} ({$status}).{$hint}";

            return null;
        }

        $daily = $this->rates->dailyRate($profile, $date);
        $mult = $this->calc->multiplier('regular_holiday_unworked', $date);

        return EarningLine::priced($this->calc->component('HOLIDAY_PAY'), '1', Decimal::mul($daily, $mult), $ctx->homeBranchId,
            null, null, "Unworked regular holiday ({$holiday->name}); {$reason}", $date, $this->shortDate($date));
    }

    /**
     * Omnibus Rules Book III Rule IV Secs. 6 and 10. Walk back from the holiday:
     *  - a day that is the employee's rest day, a non-working holiday, or a day the
     *    home branch is closed is skipped — unless the employee WORKED it, which
     *    counts as presence (Sec. 10 for successive holidays; DESIGN otherwise);
     *  - the first ordinary day found decides: present/late → eligible.
     *
     * @param array<string, AttendanceDay> $byDate
     * @return array{0: string, 1: bool, 2: string}|null [deciding date, eligible, note]
     */
    private function precedingWorkdayCheck(string $date, PayProfileSnapshot $fallback, array $byDate, CutoffContext $ctx): ?array
    {
        $d = new \DateTimeImmutable($date);
        for ($i = 1; $i <= self::LOOKBACK_DAYS; $i++) {
            $ymd = $d->modify("-{$i} days")->format('Y-m-d');
            $p = $ctx->timeline->on($ymd) ?? $fallback;
            $row = $byDate[$ymd] ?? null;

            $skipped = match (true) {
                $ctx->nonWorkingHoliday($ymd) !== null => 'holiday',
                $p->isRestDay($ymd)                    => 'rest day',
                $ctx->isBranchClosed($ymd)             => 'branch closed',
                default                                => null,
            };

            if ($skipped !== null) {
                if ($row !== null && $row->isWorked()) {
                    return [$ymd, true, "worked on {$ymd} ({$skipped}) before it"];
                }
                continue;
            }

            return [$ymd, $row !== null && $row->isWorked(), "present on preceding workday {$ymd}"];
        }

        return null;
    }

    /**
     * The semi-monthly line has date = null on purpose: it is not a per-day line, so
     * MinimumWageTopUp must not count it against any single day.
     *
     * @param array<string, AttendanceDay> $byDate
     * @param list<string> $warnings
     * @return list<EarningLine>
     */
    private function monthlyBasicLines(PayProfileSnapshot $p, array $byDate, CutoffContext $ctx, array &$warnings): array
    {
        $from = max($p->effectiveFrom, $ctx->periodStart);
        $to = min($p->effectiveTo ?? $ctx->periodEnd, $ctx->periodEnd);
        $cutoffDays = count($ctx->dates());
        $segDates = CutoffContext::range($from, $to);
        $segDays = count($segDates);

        $half = Decimal::mul($p->baseRate, '0.5');
        $basic = $this->calc->component('BASIC');

        if ($segDays === $cutoffDays) {
            $first = EarningLine::priced($basic, '1', $half, $ctx->homeBranchId, null, null,
                'Semi-monthly basic: ½ of ₱'.Decimal::peso($p->baseRate), null, 'semi-monthly');
        } else {
            // Proration by calendar days — UNVERIFIED.
            $first = EarningLine::withAmount($basic, (string) $segDays, Decimal::div($half, (string) $cutoffDays),
                Decimal::div(Decimal::mul($half, (string) $segDays), (string) $cutoffDays), $ctx->homeBranchId, null, null,
                sprintf('Semi-monthly basic prorated: ½ of ₱%s × %d/%d days (profile %s to %s)', Decimal::peso($p->baseRate), $segDays, $cutoffDays, $from, $to),
                null, 'semi-monthly (prorated)');
            $warnings[] = "Monthly-paid basic prorated by calendar days ({$segDays}/{$cutoffDays}) because pay profile #{$p->id} covers only {$from} to {$to} of this cutoff — review.";
        }

        $out = [$first];
        $remaining = $first->amount;
        $missing = [];

        foreach ($segDates as $date) {
            if ($p->isRestDay($date) || $ctx->nonWorkingHoliday($date) !== null) {
                continue; // paid day under the 365 factor; an absence here is not deducted
            }
            $row = $byDate[$date] ?? null;
            if ($row === null) {
                $missing[] = $date;
                continue;
            }
            if (! $row->isAbsence()) {
                continue;
            }

            $daily = $this->rates->dailyRate($p, $date);
            $deduct = Decimal::min($daily, $remaining);
            if (Decimal::cmp($deduct, '0') <= 0) {
                $warnings[] = "{$date}: absence (#{$row->id}) not deducted — this cutoff's basic pay is already fully offset by absences.";
                continue;
            }
            $capped = Decimal::cmp($deduct, $daily) < 0;
            $remaining = Decimal::sub($remaining, $deduct);

            $out[] = EarningLine::withAmount($basic, '-1', $daily, Decimal::neg($deduct), $row->branchId,
                PayslipLine::SOURCE_ATTENDANCE, $row->id,
                ucfirst(str_replace('_', ' ', $row->status)).' — unpaid day at daily rate ₱'.Decimal::peso($daily).' (monthly × 12 ÷ 365)'
                    .($capped ? '; capped at the remaining basic pay' : ''),
                $date, 'absence '.$this->shortDate($date));
        }

        if ($missing !== []) {
            $warnings[] = sprintf('%d scheduled working day(s) have no attendance record and were NOT deducted as absences: %s.', count($missing), implode(', ', $missing));
        }

        return $out;
    }

    /** @return array{0: ?string, 1: string, 2: ?string, 3: string} day key, OT key, premium code, label */
    private function classify(string $date, PayProfileSnapshot $p, CutoffContext $ctx): array
    {
        $rest = $p->isRestDay($date);
        $h = $ctx->nonWorkingHoliday($date);

        if ($h?->type === Holiday::REGULAR) {
            return $rest
                ? ['regular_holiday_rest_day', 'regular_holiday_rest_day_ot', 'HOLIDAY_PREM', "Regular holiday on rest day ({$h->name})"]
                : ['regular_holiday_worked', 'regular_holiday_ot', 'HOLIDAY_PREM', "Regular holiday ({$h->name})"];
        }

        if ($h?->type === Holiday::SPECIAL_NON_WORKING) {
            return $rest
                ? ['special_rest_day', 'special_rest_day_ot', 'HOLIDAY_PREM', "Special non-working day on rest day ({$h->name})"]
                : ['special_day', 'special_day_ot', 'HOLIDAY_PREM', "Special non-working day ({$h->name})"];
        }

        return $rest
            ? ['rest_day', 'rest_day_ot', 'RESTDAY_PREM', 'Rest day']
            : [null, 'ordinary_ot', null, 'Ordinary day'];
    }

    private function minutes(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));

        return $parts[0] * 60 + ($parts[1] ?? 0);
    }

    private function overlap(int $a1, int $a2, int $b1, int $b2): int
    {
        return max(0, min($a2, $b2) - max($a1, $b1));
    }

    private function shortDate(string $ymd): string
    {
        return (new \DateTimeImmutable($ymd))->format('M j');
    }
}
