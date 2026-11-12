<?php

declare(strict_types=1);

namespace App\Services\Staff;

/**
 * What one staff member's schedule says about one date at one branch.
 * Produced only by WorkScheduleResolver.
 */
final class ScheduledDay
{
    public const WORKING  = 'working';
    public const REST_DAY = 'rest_day';
    public const DAY_OFF  = 'day_off';   // one-off day off
    public const ON_LEAVE = 'on_leave';  // approved leave on a day they would work
    public const CLOSED   = 'closed';    // branch closed (or no hours row) that weekday

    /** Where the times / status came from. */
    public const SOURCE_BRANCH_HOURS = 'branch_hours';  // no schedule set, or a workday on branch hours
    public const SOURCE_SCHEDULE     = 'schedule';      // weekly pattern with its own times / rest day
    public const SOURCE_OVERRIDE     = 'override';      // one-off change
    public const SOURCE_LEAVE        = 'leave';
    public const SOURCE_BRANCH       = 'branch';        // branch closed
    public const SOURCE_PAY_PROFILE  = 'pay_profile';   // no schedule: rest day from the pay profile

    public function __construct(
        public readonly string $date,
        public readonly int $branchId,
        public readonly string $status,
        public readonly string $source,
        public readonly ?string $start = null,    // 'H:i' when working
        public readonly ?string $end = null,
        public readonly bool $hasSchedule = false, // false = D8 default (branch hours, no rest days)
        public readonly ?string $note = null,     // one-off change reason
        public readonly ?string $kind = null,     // one-off kind: 'unpaid' | 'swap' (Phase 4)
    ) {
    }

    public function isWorking(): bool
    {
        return $this->status === self::WORKING;
    }

    public function weekday(): string
    {
        return (new \DateTimeImmutable($this->date))->format('l');
    }

    /** Short human label, e.g. "9:00 AM – 6:00 PM", "Rest day". */
    public function label(): string
    {
        return match ($this->status) {
            self::WORKING  => self::fmt($this->start).' – '.self::fmt($this->end),
            self::REST_DAY => 'Rest day',
            self::DAY_OFF  => $this->kind === 'swap' ? 'Day off (swap)' : 'Day off',
            self::ON_LEAVE => 'On leave',
            self::CLOSED   => 'Branch closed',
        };
    }

    public static function fmt(?string $time): string
    {
        return $time === null ? '' : (new \DateTimeImmutable('2000-01-01 '.$time))->format('g:i A');
    }

    /** API / JSON shape (additive mobile fields, D7). */
    public function toArray(): array
    {
        return [
            'date'         => $this->date,
            'weekday'      => $this->weekday(),
            'branch_id'    => $this->branchId,
            'status'       => $this->status,
            'source'       => $this->source,
            'start_time'   => $this->start,
            'end_time'     => $this->end,
            'label'        => $this->label(),
            'has_schedule' => $this->hasSchedule,
            'note'         => $this->note,
            'kind'         => $this->kind,
        ];
    }
}
