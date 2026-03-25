<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Interview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplicationController extends Controller
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
            ->latest()
            ->get();

        return view('hr.applications.index', compact('applicants'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
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

    public function scheduleInterview(Request $request, Applicant $applicant)
    {
        $validated = $request->validate([
            'interview_date' => 'required|date|after_or_equal:today',
            'interview_time' => 'required',
            'remarks'        => 'nullable|string',
        ]);

        [$spa, $branchId] = $this->getSpaAndBranch();

        Interview::create([
            'applicant_id'   => $applicant->id,
            'spa_id'         => $spa->id,
            'branch_id'      => $branchId,
            'interviewed_by' => Auth::id(),
            'interview_date' => $validated['interview_date'],
            'interview_time' => $validated['interview_time'],
            'remarks'        => $validated['remarks'] ?? null,
            'status'         => 'pending',
        ]);

        $applicant->update([
            'status' => 'interview',
        ]);

        return back()->with('success', 'Interview scheduled successfully.');
    }
}