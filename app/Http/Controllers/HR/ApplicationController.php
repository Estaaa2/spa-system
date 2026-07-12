<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Interview;
use App\Models\OperatingHours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

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

        $applicants = Applicant::with('interview')
            ->where('spa_id', $spa->id)
            ->latest()
            ->get();

        return view('hr.applications.index', compact('applicants'));
    }

    public function store(Request $request)
    {
        $validated = $request->validateWithBag('application', [
            'full_name' => 'required|string|max:255',
            'email'     => 'required|email',
            'phone'     => 'nullable|string|max:20',
            'notes'     => 'nullable|string',
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
        $validator = Validator::make($request->all(), [
            'interview_date' => 'required|date|after_or_equal:today',
            'interview_time' => 'required|date_format:H:i',
            'remarks'        => 'nullable|string',
        ]);

        // ── Operating hours check ──────────────────────────────────────
        $validator->after(function ($validator) use ($request, $applicant) {
            $date = $request->input('interview_date');
            $time = $request->input('interview_time');

            if (!$date || !$time) return; // basic rules above will already flag these

            $dayOfWeek = Carbon::parse($date)->format('l'); // e.g. "Monday"

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

            $opening = substr($hours->opening_time, 0, 5); // normalize H:i:s -> H:i
            $closing = substr($hours->closing_time, 0, 5);

            if ($time < $opening || $time > $closing) {
                $validator->errors()->add(
                    'interview_time',
                    "Interview time must be between {$opening} and {$closing} on {$dayOfWeek}s."
                );
            }
        });

        $validated = $validator->validateWithBag('schedule');

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

        return back()->with('success', 'Interview scheduled successfully.')
                      ->with('schedule_reopen_applicant_id', $applicant->id)
                      ->with('schedule_reopen_applicant_name', $applicant->full_name);
    }
}
