<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\OperatingHours;
use App\Services\RecruitmentWorkflowService;
use App\Support\TableFilters;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ApplicationController extends Controller
{
    public function __construct(
        private RecruitmentWorkflowService $recruitment
    ) {
    }

    public const SOURCES = [
        'website' => 'Online',
        'manual'  => 'Walk-in',
    ];

    public const STATUSES = [
        Applicant::STATUS_PENDING   => 'Pending',
        Applicant::STATUS_INTERVIEW => 'Interview',
        Applicant::STATUS_APPROVED  => 'Approved',
        Applicant::STATUS_HIRED     => 'Hired',
        Applicant::STATUS_REJECTED  => 'Rejected',
    ];

    public function index(Request $request)
    {
        $user = Auth::user();

        $all = $this->recruitment
            ->applicantsFor($user)
            ->with([
                'interview',
                'branch',
                'jobPosting' => fn ($q) => $q->withTrashed(),
            ])
            ->latest()
            ->get();

        // Job opening options come from every applicant, not the filtered list
        // (staff scheduling & hiring Phase 5). ?posting=<id> preselects one.
        $multiBranch = $all->pluck('branch_id')->unique()->count() > 1;
        $postingOptions = $all->pluck('jobPosting')->filter()->unique('id')
            ->sortBy('title')
            ->mapWithKeys(fn ($p) => [$p->id => $p->title.($p->trashed() ? ' (deleted)' : '').($multiBranch ? ' · '.($p->branch?->name ?? '') : '')]);

        // Server-side filters (table filters workstream, Q1 A / Q2 A / Q3 A).
        $filters = [
            'q'       => TableFilters::search($request),
            'status'  => TableFilters::choice($request, 'status', array_keys(self::STATUSES)),
            'source'  => TableFilters::choice($request, 'source', array_keys(self::SOURCES)),
            'posting' => TableFilters::choice($request, 'posting', ['none', ...$postingOptions->keys()->all()]),
        ];

        $applicants = $all->filter(fn (Applicant $a) =>
            TableFilters::matches($filters['q'], $a->full_name, $a->email, $a->phone)
            && ($filters['status'] === null || $a->status === $filters['status'])
            && ($filters['source'] === null || $a->source === $filters['source'])
            && ($filters['posting'] === null
                || ($filters['posting'] === 'none' ? $a->job_posting_id === null : (string) $a->job_posting_id === $filters['posting']))
        )->values();

        return view('hr.applications.index', [
            'applicants'     => $applicants,
            'total'          => $all->count(),
            'filters'        => $filters,
            'activeFilters'  => TableFilters::activeCount($filters),
            'postingOptions' => $postingOptions,
            'sourceOptions'  => self::SOURCES,
            'statusOptions'  => self::STATUSES,
        ]);
    }

    public function scheduleInterview(Request $request, Applicant $applicant)
    {
        $user = Auth::user();

        $this->recruitment->assertApplicantAccessible($user, $applicant);

        $validator = Validator::make($request->all(), [
            'interview_date' => 'required|date|after_or_equal:today',
            'interview_time' => 'required|date_format:H:i',
            'remarks' => 'nullable|string|max:5000',
        ]);

        $validator->after(function ($validator) use ($request, $applicant) {
            $date = $request->input('interview_date');
            $time = $request->input('interview_time');

            if (!$date || !$time) {
                return;
            }

            $dayOfWeek = Carbon::parse($date)->format('l');

            $hours = OperatingHours::where('branch_id', $applicant->branch_id)
                ->where('day_of_week', $dayOfWeek)
                ->first();

            if (!$hours || $hours->is_closed) {
                $validator->errors()->add(
                    'interview_time',
                    "The spa is closed on {$dayOfWeek}s. Please choose a different date."
                );

                return;
            }

            $opening = substr($hours->opening_time, 0, 5);
            $closing = substr($hours->closing_time, 0, 5);

            if ($time < $opening || $time > $closing) {
                $validator->errors()->add(
                    'interview_time',
                    "Interview time must be between {$opening} and {$closing} on {$dayOfWeek}s."
                );
            }
        });

        $validated = $validator->validateWithBag('schedule');

        $this->recruitment->scheduleInterview(
            $user,
            $applicant,
            $validated
        );

        return back()
            ->with('success', 'Interview scheduled successfully.')
            ->with('schedule_reopen_applicant_id', $applicant->id)
            ->with('schedule_reopen_applicant_name', $applicant->full_name);
    }
}
