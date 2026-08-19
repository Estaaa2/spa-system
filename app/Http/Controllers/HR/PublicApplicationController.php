<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Branch;
use Illuminate\Http\Request;

class PublicApplicationController extends Controller
{
    public function store(Request $request, Branch $branch)
    {
        $profile = $branch->profile;

        if (!$profile || !$profile->is_hiring) {
            return back()->with('error', 'This branch is not currently accepting applications.');
        }

        $validated = $request->validate([
            'full_name'                  => 'required|string|max:255',
            'email'                      => 'required|email|max:255',
            'phone'                      => 'required|string|max:20',
            'gender'                     => 'nullable|in:male,female,other',
            'date_of_birth'              => 'nullable|date',
            'civil_status'               => 'nullable|in:single,married,widowed,separated',
            'address'                    => 'required|string|max:255',
            'position_applied'           => 'required|in:therapist,receptionist,manager,hr,finance',
            'availability'               => 'nullable|in:full_time,part_time,weekdays,weekends,shifting,flexible',
            'expected_start_date'        => 'nullable|date',
            'education'                  => 'nullable|in:high_school,vocational,undergraduate,college,postgrad',
            'resume'                     => 'required|file|mimes:pdf,doc,docx|max:5120',
            'emergency_contact_name'     => 'nullable|string|max:255',
            'emergency_contact_relation' => 'nullable|string|max:100',
            'emergency_contact_phone'    => 'nullable|string|max:20',
        ]);

        $resumePath = $request->file('resume')->store('resumes', 'public');

        Applicant::create([
            ...$validated,
            'resume_path' => $resumePath,
            'spa_id'      => $branch->spa_id,
            'branch_id'   => $branch->id,
            'source'      => 'website',
            'status'      => 'pending',
        ]);

        return back()->with('success', 'Your application has been submitted! We will contact you soon.');
    }
}
