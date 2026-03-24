<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR\Concerns\ResolvesSpaBranchContext;
use App\Mail\StaffCredentialsMail;
use App\Models\Interview;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InterviewController extends Controller
{
    use ResolvesSpaBranchContext;

    public function index()
    {
        [$spa, $branchId] = $this->getSpaAndBranch();

        $interviews = Interview::with(['applicant.jobPosting', 'interviewer'])
            ->where('spa_id', $spa->id)
            ->where('branch_id', $branchId)
            ->latest()
            ->get();

        return view('hr.interviews.index', compact('interviews'));
    }

    public function approve(Interview $interview)
    {
        $interview->update(['status' => 'approved']);
        $interview->applicant->update(['status' => 'approved']);

        return back()->with('success', 'Interview approved. You can now create a staff account.');
    }

    public function reject(Interview $interview)
    {
        $interview->update(['status' => 'rejected']);
        $interview->applicant->update(['status' => 'rejected']);

        return back()->with('success', 'Applicant rejected.');
    }

    public function createStaff(Request $request, Interview $interview)
    {
        if ($interview->staff_account_created) {
            return back()->with('error', 'Staff account already created for this applicant.');
        }

        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string|max:255',
            'roles' => 'required|in:therapist,receptionist,manager,hr,finance',
        ]);

        [$spa, $branchId] = $this->getSpaAndBranch();

        DB::transaction(function () use ($validated, $spa, $branchId, $interview) {
            $tempPassword = Str::random(12);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($tempPassword),
                'spa_id' => $spa->id,
                'branch_id' => $branchId,
                'temp_password' => $tempPassword,
                'password_reset_required' => true,
            ]);

            $user->assignRole($validated['roles']);
            $user->markEmailAsVerified();

            Staff::create([
                'user_id' => $user->id,
                'spa_id' => $spa->id,
                'branch_id' => $branchId,
                'employment_status' => 'active',
                'hire_date' => now(),
            ]);

            $interview->update(['staff_account_created' => true]);
            $interview->applicant->update(['status' => 'hired']);

            Mail::to($user->email)->send(new StaffCredentialsMail($user, $tempPassword));
        });

        return back()->with('success', 'Staff account created and credentials sent via email.');
    }
}