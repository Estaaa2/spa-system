<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\JobPosting;
use App\Models\StaffWorkSchedule;
use App\Services\RecruitmentWorkflowService;
use App\Services\Staff\WorkScheduleResolver;
use App\Support\TableFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * Job Openings (staff scheduling & hiring Phase 5, Q1 A).
 *
 * HR creates openings for the branch they have selected. An open posting shows
 * on the landing page ("We're Hiring") and is what applicants apply to.
 * Permissions: view / create / edit / delete hiring; plan feature: manpower.
 */
class JobOpeningController extends Controller
{
    public function __construct(
        private readonly RecruitmentWorkflowService $recruitment,
        private readonly WorkScheduleResolver $resolver,
    ) {
    }

    public const DISPLAY_STATUSES = [
        'open'    => 'Open',
        'draft'   => 'Draft',
        'filled'  => 'Filled',
        'closed'  => 'Closed',
        'expired' => 'Deadline passed',
    ];

    public function index(Request $request): View
    {
        $user = Auth::user();

        $all = $this->recruitment->postingsFor($user)
            ->withCount([
                'applicants',
                'applicants as hired_count' => fn ($q) => $q->where('status', Applicant::STATUS_HIRED),
            ])
            ->latest()
            ->get();

        // Server-side filters (table filters workstream).
        $filters = [
            'q'      => TableFilters::search($request),
            'status' => TableFilters::choice($request, 'status', array_keys(self::DISPLAY_STATUSES)),
            'role'   => TableFilters::choice($request, 'role', array_keys(JobPosting::ROLES)),
        ];
        $postings = $all->filter(fn (JobPosting $p) =>
            TableFilters::matches($filters['q'], $p->title)
            && ($filters['status'] === null || $p->displayStatus() === $filters['status'])
            && ($filters['role'] === null || $p->role === $filters['role'])
        )->values();

        $branchHours = [];
        foreach ($this->resolver->branchHours((int) $user->currentBranchId()) as $day => $h) {
            $branchHours[$day] = [
                'closed' => (bool) $h->is_closed,
                'open'   => $h->is_closed ? null : substr((string) $h->opening_time, 0, 5),
                'close'  => $h->is_closed ? null : substr((string) $h->closing_time, 0, 5),
            ];
        }

        return view('hr.job-openings.index', [
            'postings'      => $postings,
            'total'         => $all->count(),
            'filters'       => $filters,
            'activeFilters' => TableFilters::activeCount($filters),
            'branchHours' => $branchHours,
            'hours'       => $this->resolver->branchHours((int) $user->currentBranchId()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $branchId = $user->currentBranchId();
        abort_unless($user->spa_id && $branchId, 403);

        $data = $this->validated($request);

        if ($data['status'] === JobPosting::STATUS_CLOSED) {
            $data['closed_reason'] = JobPosting::CLOSED_MANUAL;
            $data['closed_at'] = now();
        }

        JobPosting::create([
            ...$data,
            'spa_id'     => $user->spa_id,
            'branch_id'  => $branchId,
            'created_by' => $user->id,
        ]);

        return redirect()->route('job-openings.index')->with('success', 'Job opening saved.');
    }

    public function update(Request $request, JobPosting $posting): RedirectResponse
    {
        $this->recruitment->assertPostingAccessible(Auth::user(), $posting);

        $data = $this->validated($request, $posting);

        // Raising the openings above the hires reopens a filled posting only when
        // HR also picks "Open"; a filled posting stays closed otherwise.
        if ($data['status'] === JobPosting::STATUS_OPEN) {
            $data['closed_reason'] = null;
            $data['closed_at'] = null;
        } elseif ($data['status'] === JobPosting::STATUS_CLOSED && $posting->status !== JobPosting::STATUS_CLOSED) {
            $data['closed_reason'] = JobPosting::CLOSED_MANUAL;
            $data['closed_at'] = now();
        }

        $posting->update($data);

        return redirect()->route('job-openings.index')->with('success', 'Job opening updated.');
    }

    /** Close or reopen from the list. */
    public function updateStatus(Request $request, JobPosting $posting): RedirectResponse
    {
        $this->recruitment->assertPostingAccessible(Auth::user(), $posting);

        $validated = $request->validate(['status' => ['required', Rule::in([JobPosting::STATUS_OPEN, JobPosting::STATUS_CLOSED])]]);

        if ($validated['status'] === JobPosting::STATUS_OPEN) {
            $hired = $posting->hiredCount();
            if ($hired >= $posting->openings) {
                return back()->with('error', "All {$posting->openings} ".str('opening')->plural($posting->openings)." in this posting are filled. Edit it and raise the number of openings to reopen it.");
            }
            if ($posting->isExpired()) {
                return back()->with('error', 'The deadline has passed. Edit the posting and change or clear the deadline to reopen it.');
            }

            $posting->update(['status' => JobPosting::STATUS_OPEN, 'closed_reason' => null, 'closed_at' => null]);

            return back()->with('success', 'Job opening reopened.');
        }

        $posting->update([
            'status'        => JobPosting::STATUS_CLOSED,
            'closed_reason' => JobPosting::CLOSED_MANUAL,
            'closed_at'     => now(),
        ]);

        return back()->with('success', 'Job opening closed.');
    }

    public function destroy(JobPosting $posting): RedirectResponse
    {
        $this->recruitment->assertPostingAccessible(Auth::user(), $posting);

        // Soft delete: applicants keep their link (job_posting_id) for history.
        $posting->delete();

        return redirect()->route('job-openings.index')->with('success', 'Job opening deleted. Its applicants are kept.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?JobPosting $posting = null): array
    {
        $spa = Auth::user()->spa;

        $rules = [
            'title'           => ['required', 'string', 'max:100'],
            'role'            => ['required', Rule::in(array_keys(JobPosting::ROLES))],
            'openings'        => ['required', 'integer', 'min:1', 'max:50'],
            'employment_type' => ['required', Rule::in(array_keys(JobPosting::EMPLOYMENT_TYPES))],
            'description'     => ['nullable', 'string', 'max:2000'],
            'requirements'    => ['nullable', 'string', 'max:2000'],
            'pay_basis'       => ['nullable', Rule::in(array_keys(JobPosting::PAY_BASES))],
            'pay_min'         => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'pay_max'         => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'show_pay'        => ['nullable', 'boolean'],
            'status'          => ['required', Rule::in([JobPosting::STATUS_OPEN, JobPosting::STATUS_DRAFT, JobPosting::STATUS_CLOSED])],
            'deadline'        => ['nullable', 'date_format:Y-m-d'],
            'schedule'        => ['required', 'array'],
        ];
        $messages = [
            'title.required'    => 'Enter a job title.',
            'openings.min'      => 'There must be at least 1 opening.',
            'schedule.required' => 'Set the weekly schedule.',
        ];

        foreach (StaffWorkSchedule::WEEKDAYS as $d) {
            $rules["schedule.$d"] = ['required', 'array'];
            $rules["schedule.$d.mode"] = ['required', 'in:rest,branch,custom'];
            $rules["schedule.$d.start"] = ["required_if:schedule.$d.mode,custom", 'nullable', 'date_format:H:i'];
            $rules["schedule.$d.end"] = ["required_if:schedule.$d.mode,custom", 'nullable', 'date_format:H:i'];
            $messages["schedule.$d.start.required_if"] = "Enter {$d}'s start time.";
            $messages["schedule.$d.end.required_if"] = "Enter {$d}'s end time.";
            $messages["schedule.$d.start.date_format"] = "{$d}'s start time is not a valid time.";
            $messages["schedule.$d.end.date_format"] = "{$d}'s end time is not a valid time.";
        }

        $validator = validator($request->all(), $rules, $messages)
            ->after(function (Validator $v) use ($request, $spa, $posting) {
                $days = (array) $request->input('schedule', []);
                $rest = count(array_filter($days, fn ($d) => ($d['mode'] ?? null) === 'rest'));

                if ($rest === 0) {
                    $v->errors()->add('schedule', 'Pick at least one rest day. The Labor Code (Art. 91) gives every employee 24 consecutive hours of rest after six workdays.');
                }
                if ($rest === 7) {
                    $v->errors()->add('schedule', 'Every day is a rest day. Set at least one workday.');
                }
                // Same rule as the Staff page (Phase 4): daily-paid pay needs 1 or 2 rest days.
                if ($request->input('pay_basis') === 'daily' && $rest > 2) {
                    $v->errors()->add('schedule', "Daily-paid jobs need 1 or 2 rest days a week (DOLE factor 313 or 261), so payroll can price them. This schedule has {$rest}.");
                }

                foreach (StaffWorkSchedule::WEEKDAYS as $d) {
                    $day = $days[$d] ?? [];
                    if (($day['mode'] ?? null) === 'custom' && ! empty($day['start']) && ! empty($day['end']) && $day['end'] <= $day['start']) {
                        $v->errors()->add("schedule.$d.end", "{$d}'s end time must be after its start time.");
                    }
                }

                $min = $request->input('pay_min');
                $max = $request->input('pay_max');
                if (is_numeric($min) && is_numeric($max) && (float) $max < (float) $min) {
                    $v->errors()->add('pay_max', 'The highest pay must be the same as or more than the lowest pay.');
                }
                if ($request->boolean('show_pay') && ! $request->filled('pay_basis')) {
                    $v->errors()->add('pay_basis', 'Pick how the job is paid, or turn off "Show pay".');
                }

                if (in_array($request->input('role'), ['hr', 'finance'], true) && ! ($spa?->hasFeature('manpower') ?? false)) {
                    $v->errors()->add('role', 'HR and Finance accounts require the Premium plan.');
                }

                $deadline = $request->input('deadline');
                if ($request->input('status') === JobPosting::STATUS_OPEN
                    && is_string($deadline) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)
                    && $deadline < now()->toDateString()) {
                    $v->errors()->add('deadline', 'An open posting needs a deadline of today or later, or no deadline.');
                }

                if ($posting && $request->input('status') === JobPosting::STATUS_OPEN) {
                    $hired = $posting->hiredCount();
                    if ((int) $request->input('openings') <= $hired) {
                        $v->errors()->add('openings', "{$hired} already hired from this posting. Raise the openings above {$hired} to keep it open, or close it.");
                    }
                }
            });

        // Errors go to the "posting" bag so the modal reopens with them.
        $validator->validateWithBag('posting');
        $data = $validator->validated();

        $schedule = RecruitmentWorkflowService::patternFromPosting($data['schedule']);

        $hasPay = ! empty($data['pay_basis']);
        $commission = ($data['pay_basis'] ?? null) === 'commission';

        return [
            'title'           => trim($data['title']),
            'role'            => $data['role'],
            'openings'        => (int) $data['openings'],
            'employment_type' => $data['employment_type'],
            'description'     => $data['description'] ?? null,
            'requirements'    => $data['requirements'] ?? null,
            'schedule'        => $schedule,
            'pay_basis'       => $data['pay_basis'] ?? null,
            'pay_min'         => $hasPay && ! $commission ? ($data['pay_min'] ?? null) : null,
            'pay_max'         => $hasPay && ! $commission ? ($data['pay_max'] ?? null) : null,
            'show_pay'        => $hasPay && $request->boolean('show_pay'),
            'status'          => $data['status'],
            'deadline'        => $data['deadline'] ?? null,
        ];
    }
}
