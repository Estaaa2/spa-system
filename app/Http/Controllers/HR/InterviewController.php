<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use App\Services\RecruitmentWorkflowService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class InterviewController extends Controller
{
    public function __construct(
        private RecruitmentWorkflowService $recruitment
    ) {
    }

    public function index()
    {
        $user = Auth::user();

        $interviews = $this->recruitment
            ->interviewsFor($user)
            ->with([
                'applicant.jobPosting',
                'applicant.branch',
                'interviewer',
            ])
            ->latest()
            ->get();

        return view('hr.interviews.index', compact('interviews'));
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
        ]);

        $this->recruitment->rejectInterview(
            Auth::user(),
            $interview,
            $validated['rejection_reason']
        );

        return back()->with(
            'success',
            'Applicant rejected and rejection reason recorded.'
        );
    }

    public function createStaff(Interview $interview)
    {
        $this->recruitment->hireApplicant(
            Auth::user(),
            $interview
        );

        return back()->with(
            'success',
            'Staff account created and credentials processing completed.'
        );
    }
}
