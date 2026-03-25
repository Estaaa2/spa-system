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
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',

            'position_applied' => 'required|in:therapist,receptionist,manager,hr,finance',
            'availability' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',

            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'civil_status' => 'nullable|string|max:255',
            'address' => 'required|string|max:255',
            'education' => 'nullable|string|max:255',
            'work_experience' => 'nullable|string',
            'skills' => 'nullable|string',

            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_relation' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:255',

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

    // Optional: remove these if Hiring no longer manages job postings
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