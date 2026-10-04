<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\OperatingHours;
use App\Services\RecruitmentWorkflowService;
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

    public function index()
    {
        $user = Auth::user();

        $applicants = $this->recruitment
            ->applicantsFor($user)
            ->with([
                'interview',
                'branch',
            ])
            ->latest()
            ->get();

        return view('hr.applications.index', compact('applicants'));
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
