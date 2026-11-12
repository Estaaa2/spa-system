<?php

namespace App\Models;

use App\Services\Hiring\HiringStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A job opening (staff scheduling & hiring Phase 5).
 *
 * "Open" means status open and the deadline (if any) not yet passed. A posting
 * closes by itself when the hires reach the number of openings (closed_reason
 * "filled") and stops showing once its deadline passes (Q6 A); HR can reopen.
 */
class JobPosting extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_DRAFT = 'draft';

    public const CLOSED_FILLED = 'filled';
    public const CLOSED_MANUAL = 'manual';

    public const ROLES = [
        'therapist'    => 'Therapist',
        'receptionist' => 'Receptionist',
        'manager'      => 'Manager',
        'hr'           => 'HR',
        'finance'      => 'Finance',
    ];

    public const EMPLOYMENT_TYPES = [
        'full_time'   => 'Full-time',
        'part_time'   => 'Part-time',
        'contractual' => 'Contractual',
    ];

    public const PAY_BASES = [
        'daily'      => 'Daily rate',
        'monthly'    => 'Monthly salary',
        'commission' => 'Commission-based',
    ];

    protected $fillable = [
        'spa_id', 'branch_id', 'created_by',
        'title', 'role', 'openings', 'employment_type', 'schedule',
        'description', 'requirements',
        'pay_basis', 'pay_min', 'pay_max', 'show_pay',
        'status', 'closed_reason', 'closed_at', 'deadline',
    ];

    protected $casts = [
        'deadline'  => 'date',
        'schedule'  => 'array',
        'openings'  => 'integer',
        'pay_min'   => 'decimal:2',
        'pay_max'   => 'decimal:2',
        'show_pay'  => 'boolean',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // The landing badge and hiring note are worked out from open postings.
        $flush = fn () => app(HiringStatus::class)->flush();
        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
    }

    public function spa()        { return $this->belongsTo(Spa::class); }
    public function branch()     { return $this->belongsTo(Branch::class); }
    public function creator()    { return $this->belongsTo(User::class, 'created_by'); }
    public function applicants() { return $this->hasMany(Applicant::class); }

    /** Open and not past its deadline. */
    public function scopeOpen(Builder $query): Builder
    {
        $today = Carbon::today()->toDateString();

        return $query->where('status', self::STATUS_OPEN)
            ->where(fn (Builder $q) => $q->whereNull('deadline')->orWhereDate('deadline', '>=', $today));
    }

    public function isExpired(): bool
    {
        return $this->deadline !== null && $this->deadline->lt(Carbon::today());
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN && ! $this->isExpired();
    }

    /** open | filled | closed | expired | draft — what HR sees on the list. */
    public function displayStatus(): string
    {
        return match (true) {
            $this->status === self::STATUS_DRAFT => 'draft',
            $this->status === self::STATUS_CLOSED => $this->closed_reason === self::CLOSED_FILLED ? 'filled' : 'closed',
            $this->isExpired() => 'expired',
            default => 'open',
        };
    }

    public function hiredCount(): int
    {
        return $this->applicants()->where('status', Applicant::STATUS_HIRED)->count();
    }

    /** Close the posting when its hires reach the number of openings (Q6 A). */
    public function closeIfFilled(): bool
    {
        if ($this->status !== self::STATUS_OPEN || $this->hiredCount() < $this->openings) {
            return false;
        }

        $this->update([
            'status'        => self::STATUS_CLOSED,
            'closed_reason' => self::CLOSED_FILLED,
            'closed_at'     => now(),
        ]);

        return true;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst((string) $this->role);
    }

    public function employmentTypeLabel(): string
    {
        return self::EMPLOYMENT_TYPES[$this->employment_type] ?? ucfirst(str_replace('_', ' ', (string) $this->employment_type));
    }

    /** "₱600 – ₱750 a day", or null when pay is hidden or not set. */
    public function payLabel(bool $public = true): ?string
    {
        if (($public && ! $this->show_pay) || ! $this->pay_basis) {
            return null;
        }

        if ($this->pay_basis === 'commission') {
            return 'Commission-based';
        }

        $unit = $this->pay_basis === 'daily' ? 'a day' : 'a month';
        $fmt = fn ($v) => '₱'.number_format((float) $v, 0);

        return match (true) {
            $this->pay_min !== null && $this->pay_max !== null && (float) $this->pay_min !== (float) $this->pay_max
                => $fmt($this->pay_min).' – '.$fmt($this->pay_max).' '.$unit,
            $this->pay_min !== null => $fmt($this->pay_min).' '.$unit,
            $this->pay_max !== null => 'Up to '.$fmt($this->pay_max).' '.$unit,
            default => self::PAY_BASES[$this->pay_basis] ?? null,
        };
    }

    /**
     * One line per weekday for display, using the branch's hours for "branch" days.
     *
     * @param array<string, OperatingHours> $branchHours weekday → hours (WorkScheduleResolver::branchHours)
     * @return list<array{day: string, label: string, rest: bool}>
     */
    public function scheduleLines(array $branchHours = []): array
    {
        $lines = [];
        foreach (StaffWorkSchedule::WEEKDAYS as $day) {
            $d = $this->schedule[$day] ?? ['mode' => 'branch'];
            $mode = $d['mode'] ?? 'branch';

            if ($mode === 'rest') {
                $lines[] = ['day' => $day, 'label' => 'Rest day', 'rest' => true];
                continue;
            }

            $h = $branchHours[$day] ?? null;
            if ($mode !== 'custom' && $h?->is_closed) {
                $lines[] = ['day' => $day, 'label' => 'Branch closed', 'rest' => true];
                continue;
            }

            [$start, $end] = $mode === 'custom'
                ? [$d['start'] ?? null, $d['end'] ?? null]
                : [$h?->opening_time, $h?->closing_time];

            $lines[] = [
                'day'   => $day,
                'label' => $start && $end ? self::time($start).' – '.self::time($end) : 'Branch hours',
                'rest'  => false,
            ];
        }

        return $lines;
    }

    private static function time(string $hm): string
    {
        return Carbon::createFromFormat('H:i', substr($hm, 0, 5))->format('g:i A');
    }
}
