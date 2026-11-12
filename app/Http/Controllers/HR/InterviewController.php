<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use App\Models\User;
use App\Services\RecruitmentWorkflowService;
use Illuminate\Support\Facades\Auth;
use App\Support\TableFilters;
use Illuminate\Http\Request;

class InterviewController extends Controller
{
    public function __construct(
        private RecruitmentWorkflowService $recruitment
    ) {
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $all = $this->recruitment
            ->interviewsFor($user)
            ->with([
                'applicant.jobPosting' => fn ($q) => $q->withTrashed(),
                'applicant.branch',
                'interviewer',
            ])
            ->latest()
            ->get();

        // Summary cards count everything; the table is filtered (table filters workstream).
        $summary = [
            'total'    => $all->count(),
            'pending'  => $all->where('status', Interview::STATUS_PENDING)->count(),
            'approved' => $all->where('status', Interview::STATUS_APPROVED)->count(),
            'rejected' => $all->where('status', Interview::STATUS_REJECTED)->count(),
        ];

        $postingOptions = $all->pluck('applicant.jobPosting')->filter()->unique('id')->sortBy('title')
            ->mapWithKeys(fn ($p) => [$p->id => $p->title.($p->trashed() ? ' (deleted)' : '')]);

        $filters = [
            'q'       => TableFilters::search($request),
            'status'  => TableFilters::choice($request, 'status', [Interview::STATUS_PENDING, Interview::STATUS_APPROVED, Interview::STATUS_REJECTED]),
            'posting' => TableFilters::choice($request, 'posting', ['none', ...$postingOptions->keys()->all()]),
        ];

        $interviews = $all->filter(fn (Interview $i) =>
            TableFilters::matches($filters['q'], $i->applicant?->full_name, $i->applicant?->email)
            && ($filters['status'] === null || $i->status === $filters['status'])
            && ($filters['posting'] === null
                || ($filters['posting'] === 'none'
                    ? $i->applicant?->job_posting_id === null
                    : (string) $i->applicant?->job_posting_id === $filters['posting']))
        )->values();

        // Emails that already have a staff account in this spa (added by hand on
        // the Staff page). Those rows offer "Link Account" instead of a new one.
        $existingStaffEmails = User::query()
            ->whereHas('staff', fn ($q) => $q->where('spa_id', $user->spa_id)->whereNull('applicant_id'))
            ->whereIn('email', $interviews
                ->where('status', Interview::STATUS_APPROVED)
                ->where('staff_account_created', false)
                ->pluck('applicant.email')
                ->filter())
            ->pluck('email')
            ->map(fn ($email) => mb_strtolower($email))
            ->all();

        return view('hr.interviews.index', [
            'interviews'          => $interviews,
            'existingStaffEmails' => $existingStaffEmails,
            'summary'             => $summary,
            'filters'             => $filters,
            'activeFilters'       => TableFilters::activeCount($filters),
            'postingOptions'      => $postingOptions,
        ]);
    }

    public function approve(Interview $interview)
    {
        $this->recruitment->approveInterview(
            Auth::user(),
            $interview
        );

        return back()->with(
            'success',
            'Interview approved. You can now create a staff account.'
        );
    }

    public function reject(Request $request, Interview $interview)
    {
        $validated = $request->validateWithBag('rejectInterview', [
            'rejection_reason' => 'required|string|max:2000',
            'applicant_message' => 'nullable|string|max:2000',
        ]);

        $this->recruitment->rejectInterview(
            Auth::user(),
            $interview,
            $validated['rejection_reason'],
            $validated['applicant_message'] ?? null
        );

        return back()->with(
            'success',
            'Applicant rejected. A notification email has been sent to the applicant.'
        );
    }

    public function createStaff(Request $request, Interview $interview)
    {
        $validated = $request->validateWithBag('createStaff', [
            'first_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
        ]);

        $result = $this->recruitment->hireApplicant(
            Auth::user(),
            $interview,
            $validated
        );

        return back()->with(
            'success',
            $result['linked']
                ? 'This applicant already had a staff account. It is now linked to the interview.'
                : 'Staff account created and login credentials emailed to the applicant.'
        );
    }
}
