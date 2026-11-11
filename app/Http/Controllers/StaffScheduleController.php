<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\StaffPayProfile;
use App\Models\StaffScheduleOverride;
use App\Models\StaffWorkSchedule;
use App\Services\Payroll\PayrollPermissions;
use App\Services\Staff\ScheduledDay;
use App\Services\Staff\WorkScheduleResolver;
use App\Services\Staff\WorkScheduleWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

/**
 * Staff page → "Work schedule" modal (staff scheduling Phase 2). JSON only.
 *
 * - show:  `view staff`            — anyone who sees the Staff page can read it.
 * - write: `edit staff schedules`  — Q6 A; HR Head / Finance Head rows are
 *          owner-only, like their Edit button (Q6b A).
 * Dates: today or later, up to a year ahead (Q5 A). One-off changes apply
 * immediately (Q4 A). A pattern with no rest day is saved with a warning (Q8 A).
 */
class StaffScheduleController extends Controller
{
    private const MAX_DAYS_AHEAD = 365;

    public function __construct(
        private readonly WorkScheduleResolver $resolver,
        private readonly WorkScheduleWriter $writer,
    ) {
    }

    public function show(Staff $staff): JsonResponse
    {
        $this->assertManageable($staff);

        return response()->json($this->payload($staff));
    }

    public function update(Request $request, Staff $staff): JsonResponse
    {
        $this->assertManageable($staff);
        $this->assertCanEdit($staff);

        $validated = $this->validatePattern($request);
        $days = $this->normalizeDays($validated['days']);

        $this->writer->savePattern($staff, $validated['effective_from'], $days, Auth::id());

        $from = Carbon::parse($validated['effective_from']);

        return response()->json([
            'message'  => 'Work schedule saved. It applies from '.$from->format('M j, Y').'.',
            'warnings' => $this->patternWarnings($staff, $days),
            'schedule' => $this->payload($staff),
        ]);
    }

    public function storeOverride(Request $request, Staff $staff): JsonResponse
    {
        $this->assertManageable($staff);
        $this->assertCanEdit($staff);

        $validated = $request->validate([
            'date'       => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->today(), 'before_or_equal:'.$this->maxDate()],
            'type'       => ['required', 'in:'.StaffScheduleOverride::DAY_OFF.','.StaffScheduleOverride::SHIFT],
            'start_time' => ['required_if:type,'.StaffScheduleOverride::SHIFT, 'nullable', 'date_format:H:i'],
            'end_time'   => ['required_if:type,'.StaffScheduleOverride::SHIFT, 'nullable', 'date_format:H:i'],
            'reason'     => ['nullable', 'string', 'max:150'],
        ], [
            'date.after_or_equal'   => 'Pick today or a later date. Past days are corrected on the Attendance page.',
            'date.before_or_equal'  => 'Pick a date within the next 12 months.',
            'start_time.required_if' => 'Enter the shift start time.',
            'end_time.required_if'   => 'Enter the shift end time.',
        ]);

        $isShift = $validated['type'] === StaffScheduleOverride::SHIFT;
        $date = $validated['date'];
        $name = $this->firstName($staff);

        if ($isShift && $validated['end_time'] <= $validated['start_time']) {
            return $this->fieldError('end_time', 'The end time must be after the start time.');
        }

        $branchId = (int) $staff->branch_id;
        $weekday = Carbon::parse($date)->format('l');
        $hours = $this->resolver->branchHours($branchId)[$weekday] ?? null;
        if ($isShift && ($hours === null || $hours->is_closed)) {
            return $this->fieldError('date', "The branch is closed on {$weekday}s, so a shift can't be worked that day.");
        }

        if ($isShift && $this->hasApprovedLeave($staff, $date)) {
            return $this->fieldError('date', "{$name} has approved leave on ".Carbon::parse($date)->format('M j').'. Cancel or change the leave first, or pick another date.');
        }

        $existing = StaffScheduleOverride::where('staff_id', $staff->id)->whereDate('date', $date)->exists();

        $this->writer->saveOverride(
            $staff, $date, $validated['type'],
            $validated['start_time'] ?? null, $validated['end_time'] ?? null,
            $validated['reason'] ?? null, Auth::id()
        );

        $label = Carbon::parse($date)->format('D, M j');

        return response()->json([
            'message'  => ($existing ? 'Change replaced for ' : 'Change added for ').$label.'.',
            'warnings' => $isShift ? $this->outsideHoursNote($validated['start_time'], $validated['end_time'], $hours, $label) : [],
            'schedule' => $this->payload($staff),
        ]);
    }

    public function destroyOverride(Staff $staff, StaffScheduleOverride $override): JsonResponse
    {
        $this->assertManageable($staff);
        $this->assertCanEdit($staff);
        abort_unless((int) $override->staff_id === (int) $staff->id, 404);

        if ($override->date->toDateString() < $this->today()) {
            return response()->json(['message' => 'Past changes can\'t be removed.'], 422);
        }

        $this->writer->deleteOverride($override);

        return response()->json([
            'message'  => 'Change removed for '.$override->date->format('D, M j').'.',
            'warnings' => [],
            'schedule' => $this->payload($staff),
        ]);
    }

    // ── Payload ──────────────────────────────────────────────────────────────

    private function payload(Staff $staff): array
    {
        $staff->loadMissing(['user', 'branch']);
        $today = $this->today();
        $branchId = (int) $staff->branch_id;

        $branchHours = [];
        $hours = $this->resolver->branchHours($branchId);
        foreach (StaffWorkSchedule::WEEKDAYS as $d) {
            $h = $hours[$d] ?? null;
            $closed = $h === null || $h->is_closed || ! $h->opening_time || ! $h->closing_time;
            $branchHours[$d] = [
                'closed' => $closed,
                'open'   => $closed ? null : substr((string) $h->opening_time, 0, 5),
                'close'  => $closed ? null : substr((string) $h->closing_time, 0, 5),
                'label'  => $closed ? 'Closed' : ScheduledDay::fmt($h->opening_time).' – '.ScheduledDay::fmt($h->closing_time),
            ];
        }

        $schedules = StaffWorkSchedule::with('days')->where('staff_id', $staff->id)->orderByDesc('effective_from')->get();
        $current = $this->resolver->scheduleOn($staff, $today);
        // The editor opens on the newest version: saving replaces it anyway.
        $editing = $schedules->first();

        $upcoming = $schedules
            ->filter(fn ($s) => $s->effective_from->toDateString() > $today)
            ->sortBy('effective_from')
            ->map(fn ($s) => [
                'effective_from'       => $s->effective_from->toDateString(),
                'effective_from_label' => $s->effective_from->format('M j, Y'),
                'rest_days'            => $s->restDays(),
            ])->values();

        $overrides = StaffScheduleOverride::where('staff_id', $staff->id)
            ->whereDate('date', '>=', $today)
            ->orderBy('date')
            ->get()
            ->map(fn (StaffScheduleOverride $o) => [
                'id'         => $o->id,
                'date'       => $o->date->toDateString(),
                'date_label' => $o->date->format('D, M j, Y'),
                'type'       => $o->type,
                'start_time' => $o->start_time ? substr((string) $o->start_time, 0, 5) : null,
                'end_time'   => $o->end_time ? substr((string) $o->end_time, 0, 5) : null,
                'label'      => $o->isDayOff() ? 'Day off' : ScheduledDay::fmt($o->start_time).' – '.ScheduledDay::fmt($o->end_time),
                'reason'     => $o->reason,
            ])->values();

        $payProfile = StaffPayProfile::effectiveFor((int) $staff->id, $today);

        return [
            'staff' => [
                'id'          => $staff->id,
                'name'        => $staff->user ? trim($staff->user->first_name.' '.$staff->user->last_name) : '',
                'first_name'  => $this->firstName($staff),
                'branch_name' => $staff->branch?->name,
                'is_head'     => $this->isHeadAccount($staff),
            ],
            'can_edit'          => $this->canEdit($staff),
            'today'             => $today,
            'max_date'          => $this->maxDate(),
            'branch_hours'      => $branchHours,
            'has_schedule'      => $schedules->isNotEmpty(),
            'current'           => $current ? [
                'effective_from'       => $current->effective_from->toDateString(),
                'effective_from_label' => $current->effective_from->format('M j, Y'),
                'rest_days'            => $current->restDays(),
            ] : null,
            'editing'           => $editing ? [
                'effective_from' => max($editing->effective_from->toDateString(), $today),
                'days'           => $this->daysForEditor($editing),
            ] : null,
            'upcoming'          => $upcoming,
            'overrides'         => $overrides,
            'week'              => array_map(fn (ScheduledDay $d) => $d->toArray() + [
                'date_label' => Carbon::parse($d->date)->format('D, M j'),
            ], $this->resolver->forRange($staff, $today, Carbon::parse($today)->addDays(13)->toDateString())),
            'payroll_rest_days' => $payProfile ? array_values($payProfile->rest_days ?? []) : null,
        ];
    }

    /** @return array<string, array{mode: string, start: ?string, end: ?string}> */
    private function daysForEditor(StaffWorkSchedule $s): array
    {
        $out = [];
        foreach (StaffWorkSchedule::WEEKDAYS as $d) {
            $day = $s->dayFor($d);
            $mode = match (true) {
                $day === null => WorkScheduleWriter::MODE_BRANCH,
                $day->is_rest_day => WorkScheduleWriter::MODE_REST,
                $day->followsBranchHours() => WorkScheduleWriter::MODE_BRANCH,
                default => WorkScheduleWriter::MODE_CUSTOM,
            };
            $out[$d] = [
                'mode'  => $mode,
                'start' => $mode === WorkScheduleWriter::MODE_CUSTOM ? substr((string) $day->start_time, 0, 5) : null,
                'end'   => $mode === WorkScheduleWriter::MODE_CUSTOM ? substr((string) $day->end_time, 0, 5) : null,
            ];
        }

        return $out;
    }

    // ── Validation ───────────────────────────────────────────────────────────

    private function validatePattern(Request $request): array
    {
        $rules = [
            'effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->today(), 'before_or_equal:'.$this->maxDate()],
            'days'           => ['required', 'array'],
        ];
        $messages = [
            'effective_from.after_or_equal'  => 'The schedule can start today or later. Past days are corrected on the Attendance page.',
            'effective_from.before_or_equal' => 'Pick a start date within the next 12 months.',
        ];
        foreach (StaffWorkSchedule::WEEKDAYS as $d) {
            $rules["days.$d"] = ['required', 'array'];
            $rules["days.$d.mode"] = ['required', 'in:rest,branch,custom'];
            $rules["days.$d.start"] = ["required_if:days.$d.mode,custom", 'nullable', 'date_format:H:i'];
            $rules["days.$d.end"] = ["required_if:days.$d.mode,custom", 'nullable', 'date_format:H:i'];
            $messages["days.$d.start.required_if"] = "Enter {$d}'s start time.";
            $messages["days.$d.end.required_if"] = "Enter {$d}'s end time.";
            $messages["days.$d.start.date_format"] = "{$d}'s start time is not a valid time.";
            $messages["days.$d.end.date_format"] = "{$d}'s end time is not a valid time.";
        }

        return validator($request->all(), $rules, $messages)
            ->after(function (Validator $v) use ($request) {
                foreach (StaffWorkSchedule::WEEKDAYS as $d) {
                    $day = $request->input("days.$d");
                    if (($day['mode'] ?? null) === 'custom'
                        && ! empty($day['start']) && ! empty($day['end'])
                        && $day['end'] <= $day['start']) {
                        $v->errors()->add("days.$d.end", "{$d}'s end time must be after its start time.");
                    }
                }
            })
            ->validate();
    }

    /** @return array<string, array{mode: string, start?: ?string, end?: ?string}> */
    private function normalizeDays(array $input): array
    {
        $out = [];
        foreach (StaffWorkSchedule::WEEKDAYS as $d) {
            $mode = $input[$d]['mode'];
            $out[$d] = $mode === WorkScheduleWriter::MODE_CUSTOM
                ? ['mode' => $mode, 'start' => $input[$d]['start'], 'end' => $input[$d]['end']]
                : ['mode' => $mode];
        }

        return $out;
    }

    /** @return list<string> */
    private function patternWarnings(Staff $staff, array $days): array
    {
        $warnings = [];
        if (! WorkScheduleWriter::hasRestDay($days)) {
            $warnings[] = 'No rest day is set. The Labor Code (Art. 91) gives every employee at least 24 consecutive hours of rest after six consecutive workdays.';
        }

        $hours = $this->resolver->branchHours((int) $staff->branch_id);
        foreach ($days as $weekday => $d) {
            $h = $hours[$weekday] ?? null;
            $closed = $h === null || $h->is_closed;
            if ($closed && $d['mode'] !== WorkScheduleWriter::MODE_REST) {
                $warnings[] = "The branch is closed on {$weekday}s, so that day won't be worked.";
            } elseif (! $closed && $d['mode'] === WorkScheduleWriter::MODE_CUSTOM) {
                $warnings = [...$warnings, ...$this->outsideHoursNote($d['start'], $d['end'], $h, $weekday)];
            }
        }

        $payProfile = StaffPayProfile::effectiveFor((int) $staff->id, $this->today());
        if ($payProfile) {
            $scheduleRest = array_keys(array_filter($days, fn ($d) => $d['mode'] === WorkScheduleWriter::MODE_REST));
            $payRest = array_values($payProfile->rest_days ?? []);
            sort($scheduleRest);
            sort($payRest);
            if ($scheduleRest !== $payRest) {
                $warnings[] = 'Payroll still uses the rest days in this staff member\'s pay profile ('
                    .($payRest ? implode(', ', $payRest) : 'none').'). Update the pay profile too until the two are linked.';
            }
        }

        return $warnings;
    }

    /** @return list<string> */
    private function outsideHoursNote(?string $start, ?string $end, $hours, string $when): array
    {
        if (! $hours || $hours->is_closed || ! $start || ! $end) {
            return [];
        }
        $open = substr((string) $hours->opening_time, 0, 5);
        $close = substr((string) $hours->closing_time, 0, 5);

        return ($start < $open || $end > $close)
            ? ["{$when}: the shift runs outside branch hours (".ScheduledDay::fmt($open).' – '.ScheduledDay::fmt($close).'), e.g. for opening or closing duties.']
            : [];
    }

    // ── Access ───────────────────────────────────────────────────────────────

    /** Same scope as StaffController: own spa; own branch unless the user can switch branches. */
    private function assertManageable(Staff $staff): void
    {
        $user = Auth::user();
        abort_unless((int) $staff->spa_id === (int) $user->spa_id, 404);
        if (! $user->canSwitchBranches()) {
            abort_unless((int) $staff->branch_id === (int) $user->branch_id, 403, 'You can only manage staff of your own branch.');
        }
    }

    private function canEdit(Staff $staff): bool
    {
        $user = Auth::user();

        return $user->hasBranchPermission('edit staff schedules')
            && ($user->hasRole('owner') || ! $this->isHeadAccount($staff));
    }

    private function assertCanEdit(Staff $staff): void
    {
        abort_unless($this->canEdit($staff), 403, $this->isHeadAccount($staff)
            ? 'Only the owner can change the HR Head\'s and Finance Head\'s schedules.'
            : 'You don\'t have permission to edit work schedules.');
    }

    private function isHeadAccount(Staff $staff): bool
    {
        return (bool) $staff->user?->hasAnyRole(PayrollPermissions::HEAD_ROLES);
    }

    private function hasApprovedLeave(Staff $staff, string $date): bool
    {
        return $staff->user_id && LeaveRequest::query()
            ->where('user_id', $staff->user_id)
            ->where('spa_id', $staff->spa_id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    private function fieldError(string $field, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }

    private function firstName(Staff $staff): string
    {
        return $staff->user?->first_name ?: 'This staff member';
    }

    private function today(): string
    {
        return today()->toDateString();
    }

    private function maxDate(): string
    {
        return today()->addDays(self::MAX_DAYS_AHEAD)->toDateString();
    }
}
