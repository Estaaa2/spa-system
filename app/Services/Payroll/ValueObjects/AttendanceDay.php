<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

use App\Models\StaffAttendance;

/** Plain copy of a staff_attendance row. Times are 'H:i:s' (or 'H:i') strings. */
final readonly class AttendanceDay
{
    public const WORKED = ['present', 'late'];     // late counts as present (late deductions are CUT)
    public const ABSENT = ['absent', 'on_leave'];  // on_leave is unpaid in v3 (SIL is CUT)

    public function __construct(
        public int $id,
        public string $date,
        public int $branchId,
        public string $status,
        public ?string $timeIn,
        public ?string $timeOut,
        public bool $autoClosed = false,
    ) {
    }

    public static function fromModel(StaffAttendance $a): self
    {
        return new self(
            id: (int) $a->id,
            date: $a->date->toDateString(),
            branchId: (int) $a->branch_id,
            status: (string) $a->status,
            timeIn: $a->time_in ?: null,
            timeOut: $a->time_out ?: null,
            autoClosed: (bool) $a->auto_closed,
        );
    }

    public function isWorked(): bool
    {
        return in_array($this->status, self::WORKED, true);
    }

    public function isAbsence(): bool
    {
        return in_array($this->status, self::ABSENT, true);
    }
}
