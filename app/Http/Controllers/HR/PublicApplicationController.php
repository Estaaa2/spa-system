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
        try {
            $profile = $branch->profile;

            if (!$profile || !$profile->is_hiring) {
                return response()->json([
                    'success' => false,
                    'message' => 'This branch is not currently accepting applications.'
                ], 400);
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

            return response()->json([
                'success' => true,
                'message' => 'Your application has been submitted! We will contact you soon.'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'message' => 'Validation failed.'
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}
