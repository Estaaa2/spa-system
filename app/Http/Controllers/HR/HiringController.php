<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HiringController extends Controller
{
    private function getSpaAndBranch()
    {
        $user     = Auth::user();
        $spa      = $user->spa;
        $branchId = $user->currentBranchId();
        return [$spa, $branchId];
    }

    public function index()
    {
        [$spa, $branchId] = $this->getSpaAndBranch();

        $applicants = Applicant::where('spa_id', $spa->id)
            ->where('branch_id', $branchId)
            ->latest()
            ->get();

        return view('hr.hiring.index', compact('applicants'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|max:20',
            'role' => 'required|in:therapist,receptionist,manager,hr,finance',
            'gender' => 'required|in:male,female,other',
            'date_of_birth' => 'required|date',
            'civil_status' => 'nullable|string',
            'address' => 'required|string',
            'education' => 'nullable|string',
            'work_experience' => 'nullable|string',
            'skills' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string',
            'emergency_contact_relation' => 'nullable|string',
            'emergency_contact_phone' => 'nullable|string',
            'expected_start_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        [$spa, $branchId] = $this->getSpaAndBranch();

        Applicant::create([
            ...$validated,
            'spa_id' => $spa->id,
            'branch_id' => $branchId,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Application submitted successfully.');
    }

    public function update(Request $request, JobPosting $posting)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,closed,draft',
        ]);

        $posting->update($validated);

        return back()->with('success', 'Job posting updated.');
    }

    public function destroy(JobPosting $posting)
    {
        $posting->delete();

        return back()->with('success', 'Job posting deleted.');
    }
}