<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Mail\StaffCredentialsMail;
use App\Models\Applicant;
use App\Models\Interview;
use App\Models\JobPosting;
use App\Models\Payroll;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class HRController extends Controller
{
    private function getSpaAndBranch()
    {
        $user     = Auth::user();
        $spa      = $user->spa;
        $branchId = $user->currentBranchId();
        return [$spa, $branchId];
    }

    // ==================== DASHBOARD ====================
    public function dashboard()
    {
        [$spa, $branchId] = $this->getSpaAndBranch();

        $stats = [
            'open_jobs'         => JobPosting::where('spa_id', $spa->id)->where('status', 'open')->count(),
            'pending_applicants'=> Applicant::where('spa_id', $spa->id)->where('status', 'pending')->count(),
            'pending_interviews'=> Interview::where('spa_id', $spa->id)->where('status', 'pending')->count(),
            'total_staff'       => Staff::where('spa_id', $spa->id)->where('branch_id', $branchId)->count(),
        ];

        $recentApplications = Applicant::with('jobPosting')
            ->where('spa_id', $spa->id)
            ->latest()
            ->take(5)
            ->get();

        $upcomingInterviews = Interview::with('applicant.jobPosting')
            ->where('spa_id', $spa->id)
            ->where('status', 'pending')
            ->where('interview_date', '>=', today())
            ->orderBy('interview_date')
            ->take(5)
            ->get();

        return view('hr.dashboard', compact('stats', 'recentApplications', 'upcomingInterviews'));
    }

    // ==================== HIRING ====================
    public function hiring()
    {
        [$spa, $branchId] = $this->getSpaAndBranch();

        // ✅ No more job postings — just applicants directly
        $applicants = Applicant::where('spa_id', $spa->id)
            ->latest()
            ->get();

        return view('hr.hiring', compact('applicants'));
    }

    public function hiringStore(Request $request)
    {
        $validated = $request->validate([
            'full_name'                  => 'required|string|max:255',
            'email'                      => 'required|email',
            'phone'                      => 'required|string|max:20',
            'role'                       => 'required|in:therapist,receptionist,manager,hr,finance',
            'gender'                     => 'required|in:male,female,other',
            'date_of_birth'              => 'required|date',
            'civil_status'               => 'nullable|string',
            'address'                    => 'required|string',
            'education'                  => 'nullable|string',
            'work_experience'            => 'nullable|string',
            'skills'                     => 'nullable|string',
            'emergency_contact_name'     => 'nullable|string',
            'emergency_contact_relation' => 'nullable|string',
            'emergency_contact_phone'    => 'nullable|string',
            'expected_start_date'        => 'nullable|date',
            'notes'                      => 'nullable|string',
        ]);

        [$spa, $branchId] = $this->getSpaAndBranch();

        Applicant::create([
            ...$validated,
            'spa_id'    => $spa->id,
            'branch_id' => $branchId,
            'status'    => 'pending',
        ]);

        return back()->with('success', 'Application submitted successfully.');
    }

    public function hiringUpdate(Request $request, JobPosting $posting)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,closed,draft',
        ]);

        $posting->update($validated);

        return back()->with('success', 'Job posting updated.');
    }

    public function hiringDestroy(JobPosting $posting)
    {
        $posting->delete();
        return back()->with('success', 'Job posting deleted.');
    }

    // ==================== APPLICATIONS ====================
    public function applications()
    {
        [$spa, $branchId] = $this->getSpaAndBranch();

        // ✅ Just load applicants — no job postings needed
        $applicants = Applicant::where('spa_id', $spa->id)
            ->latest()
            ->get();

        return view('hr.applications', compact('applicants'));
    }

    public function applicationsStore(Request $request)
    {
        $validated = $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
            'full_name'      => 'required|string|max:255',
            'email'          => 'required|email',
            'phone'          => 'nullable|string|max:20',
            'notes'          => 'nullable|string',
        ]);

        [$spa, $branchId] = $this->getSpaAndBranch();

        Applicant::create([
            ...$validated,
            'spa_id'    => $spa->id,
            'branch_id' => $branchId,
            'status'    => 'pending',
        ]);

        return back()->with('success', 'Applicant added successfully.');
    }

    public function applicationsScheduleInterview(Request $request, Applicant $applicant)
    {
        $validated = $request->validate([
            'interview_date' => 'required|date|after_or_equal:today',
            'interview_time' => 'required',
            'remarks'        => 'nullable|string',
        ]);

        [$spa, $branchId] = $this->getSpaAndBranch();

        Interview::create([
            'applicant_id'    => $applicant->id,
            'spa_id'          => $spa->id,
            'branch_id'       => $branchId,
            'interviewed_by'  => Auth::id(),
            'interview_date'  => $validated['interview_date'],
            'interview_time'  => $validated['interview_time'],
            'remarks'         => $validated['remarks'] ?? null,
            'status'          => 'pending',
        ]);

        $applicant->update(['status' => 'interview']);

        return back()->with('success', 'Interview scheduled successfully.');
    }

    // ==================== INTERVIEWS ====================
    public function interviews()
    {
        [$spa, $branchId] = $this->getSpaAndBranch();

        $interviews = Interview::with(['applicant.jobPosting', 'interviewer'])
            ->where('spa_id', $spa->id)
            ->latest()
            ->get();

        return view('hr.interviews', compact('interviews'));
    }

    public function interviewApprove(Interview $interview)
    {
        $interview->update(['status' => 'approved']);
        $interview->applicant->update(['status' => 'approved']);

        return back()->with('success', 'Interview approved. You can now create a staff account.');
    }

    public function interviewReject(Interview $interview)
    {
        $interview->update(['status' => 'rejected']);
        $interview->applicant->update(['status' => 'rejected']);

        return back()->with('success', 'Applicant rejected.');
    }

    public function createStaffFromInterview(Request $request, Interview $interview)
    {
        if ($interview->staff_account_created) {
            return back()->with('error', 'Staff account already created for this applicant.');
        }

        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'name'  => 'required|string|max:255',
            'roles' => 'required|in:therapist,receptionist,manager,hr,finance',
        ]);

        [$spa, $branchId] = $this->getSpaAndBranch();

        DB::transaction(function () use ($validated, $spa, $branchId, $interview) {
            $tempPassword = Str::random(12);

            $user = User::create([
                'name'                    => $validated['name'],
                'email'                   => $validated['email'],
                'password'                => Hash::make($tempPassword),
                'spa_id'                  => $spa->id,
                'branch_id'               => $branchId,
                'temp_password'           => $tempPassword,
                'password_reset_required' => true,
            ]);

            $user->assignRole($validated['roles']);
            $user->markEmailAsVerified();

            Staff::create([
                'user_id'           => $user->id,
                'spa_id'            => $spa->id,
                'branch_id'         => $branchId,
                'employment_status' => 'active',
                'hire_date'         => now(),
            ]);

            $interview->update(['staff_account_created' => true]);
            $interview->applicant->update(['status' => 'hired']);

            Mail::to($user->email)->send(new StaffCredentialsMail($user, $tempPassword));
        });

        return back()->with('success', 'Staff account created and credentials sent via email.');
    }

    // ==================== ATTENDANCE ====================
    public function attendance(Request $request)
    {
        [$spa, $branchId] = $this->getSpaAndBranch();

        $date = $request->date ? Carbon::parse($request->date) : today();

        $staffList = Staff::with(['user', 'attendance' => function ($q) use ($date) {
            $q->whereDate('date', $date);
        }])
            ->where('spa_id', $spa->id)
            ->where('branch_id', $branchId)
            ->where('employment_status', 'active')
            ->get();

        return view('hr.attendance', compact('staffList', 'date'));
    }

    public function attendanceStore(Request $request)
    {
        $validated = $request->validate([
            'attendance'          => 'required|array',
            'attendance.*.staff_id' => 'required|exists:staff,id',
            'attendance.*.status'   => 'required|in:present,absent,late',
            'attendance.*.remarks'  => 'nullable|string',
            'date'                  => 'required|date',
        ]);

        [$spa, $branchId] = $this->getSpaAndBranch();

        foreach ($validated['attendance'] as $record) {
            StaffAttendance::updateOrCreate(
                [
                    'staff_id' => $record['staff_id'],
                    'date'     => $validated['date'],
                ],
                [
                    'spa_id'    => $spa->id,
                    'branch_id' => $branchId,
                    'status'    => $record['status'],
                    'remarks'   => $record['remarks'] ?? null,
                ]
            );
        }

        return back()->with('success', 'Attendance saved successfully.');
    }

    // ==================== PAYROLL ====================
    public function payroll()
    {
        [$spa, $branchId] = $this->getSpaAndBranch();

        $payrolls = Payroll::with('staff.user')
            ->where('spa_id', $spa->id)
            ->where('branch_id', $branchId)
            ->latest()
            ->get();

        $staffList = Staff::with('user')
            ->where('spa_id', $spa->id)
            ->where('branch_id', $branchId)
            ->where('employment_status', 'active')
            ->get();

        return view('hr.payroll', compact('payrolls', 'staffList'));
    }

    public function payrollGenerate(Request $request)
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
        ]);

        [$spa, $branchId] = $this->getSpaAndBranch();

        $start = Carbon::parse($validated['period_start']);
        $end   = Carbon::parse($validated['period_end']);
        $label = $start->format('M d') . ' – ' . $end->format('M d, Y');

        $staffList = Staff::with(['user', 'attendance'])
            ->where('spa_id', $spa->id)
            ->where('branch_id', $branchId)
            ->where('employment_status', 'active')
            ->get();

        DB::transaction(function () use ($staffList, $spa, $branchId, $start, $end, $label, $validated) {
            foreach ($staffList as $staff) {
                $attendance = StaffAttendance::where('staff_id', $staff->id)
                    ->whereBetween('date', [$start, $end])
                    ->get();

                $daysPresent = $attendance->where('status', 'present')->count();
                $daysAbsent  = $attendance->where('status', 'absent')->count();
                $daysLate    = $attendance->where('status', 'late')->count();

                $dailyRate      = $staff->daily_rate ?? 0;
                $absentDeduction = $daysAbsent * $dailyRate;
                $lateDeduction   = $daysLate * ($dailyRate * 0.5); // 50% deduction for late

                // Commission: count bookings in period
                $commission = $staff->bookings()
                    ->whereBetween('appointment_date', [$start, $end])
                    ->where('status', 'completed')
                    ->count() * 50; // ₱50 per completed service — adjust as needed

                $totalPay = $staff->basic_salary
                    - $absentDeduction
                    - $lateDeduction
                    + $commission;

                Payroll::updateOrCreate(
                    [
                        'staff_id'     => $staff->id,
                        'period_start' => $validated['period_start'],
                        'period_end'   => $validated['period_end'],
                    ],
                    [
                        'spa_id'           => $spa->id,
                        'branch_id'        => $branchId,
                        'period_label'     => $label,
                        'basic_salary'     => $staff->basic_salary,
                        'days_present'     => $daysPresent,
                        'days_absent'      => $daysAbsent,
                        'days_late'        => $daysLate,
                        'absent_deduction' => $absentDeduction,
                        'late_deduction'   => $lateDeduction,
                        'commission'       => $commission,
                        'total_pay'        => max(0, $totalPay),
                        'status'           => 'draft',
                    ]
                );
            }
        });

        return back()->with('success', 'Payroll generated for ' . $label);
    }

    public function payrollFinalize(Payroll $payroll)
    {
        $payroll->update(['status' => 'finalized']);
        return back()->with('success', 'Payroll finalized.');
    }
}
