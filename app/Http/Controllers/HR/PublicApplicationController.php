<?php

namespace App\Http\Controllers\HR;

use App\Support\Legal\PrivacyPolicy;
use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Branch;
use App\Models\JobPosting;
use App\Services\Hiring\HiringStatus;
use App\Services\Staff\WorkScheduleResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicApplicationController extends Controller
{
    public function __construct(
        private readonly HiringStatus $hiring,
        private readonly WorkScheduleResolver $resolver,
    ) {
    }

    /**
     * Open job openings of a branch, for the landing "We're Hiring" section
     * and the Apply form (staff scheduling & hiring Phase 5, Q4 A).
     */
    public function openings(Branch $branch): JsonResponse
    {
        if (! $branch->isOperational()) {
            return response()->json(['openings' => []]);
        }

        $hours = $this->resolver->branchHours((int) $branch->id);

        $openings = $this->hiring->openings((int) $branch->id)->map(fn (JobPosting $p) => [
            'id'              => $p->id,
            'title'           => $p->title,
            'role'            => $p->role,
            'role_label'      => $p->roleLabel(),
            'openings'        => $p->openings,
            'employment_type' => $p->employmentTypeLabel(),
            'description'     => $p->description,
            'requirements'    => $p->requirements,
            'pay'             => $p->payLabel(),
            'deadline'        => $p->deadline?->format('M j, Y'),
            'schedule'        => $p->scheduleLines($hours),
        ])->values();

        return response()->json(['openings' => $openings]);
    }

    public function store(Request $request, Branch $branch)
    {
        try {
            if (! $branch->isOperational() || ! $this->hiring->isHiring((int) $branch->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This branch is not currently accepting applications.'
                ], 400);
            }

            // Phase 5: you always apply to an opening (Q8 A). No date of birth
            // (RA 10911), gender or civil status (RA 10173; Q2 A).
            $validated = $request->validate([
                'job_posting_id'             => 'required|integer',
                'full_name'                  => 'required|string|max:255',
                'email'                      => 'required|email|max:255',
                'phone'                      => 'required|string|max:20',
                'address'                    => 'required|string|max:255',
                'schedule_fit'               => 'required|boolean',
                'schedule_fit_note'          => 'nullable|string|max:255',
                'expected_start_date'        => 'nullable|date',
                'education'                  => 'nullable|in:high_school,vocational,undergraduate,college,postgrad',
                'resume'                     => 'required|file|mimes:pdf,doc,docx|max:5120',
                'emergency_contact_name'     => 'nullable|string|max:255',
                'emergency_contact_relation' => 'nullable|string|max:100',
                'emergency_contact_phone'    => 'nullable|string|max:20',
                // Consent first, recorded below (landing footer Phase 2, Q5-A).
                'privacy_consent'            => 'required|accepted',
            ], [
                'job_posting_id.required'  => 'Pick the job opening you are applying for.',
                'schedule_fit.required'    => 'Please tell us if you can work this schedule.',
                'privacy_consent.required' => 'Please agree to the privacy notice to send your application.',
                'privacy_consent.accepted' => 'Please agree to the privacy notice to send your application.',
            ]);

            $posting = $this->hiring->openings((int) $branch->id)
                ->firstWhere('id', (int) $validated['job_posting_id']);

            if (! $posting) {
                return response()->json([
                    'success' => false,
                    'errors'  => ['job_posting_id' => ['This job opening is no longer accepting applications.']],
                    'message' => 'This job opening is no longer accepting applications.',
                ], 422);
            }

            $resumePath = $request->file('resume')->store('resumes', 'local'); // private disk: personal data

            unset($validated['resume'], $validated['privacy_consent']);

            Applicant::create([
                ...$validated,
                'job_posting_id'   => $posting->id,
                'position_applied' => $posting->role, // the opening decides the role
                'resume_path'      => $resumePath,
                'spa_id'           => $branch->spa_id,
                'branch_id'        => $branch->id,
                'source'           => 'website',
                'status'           => 'pending',
                'privacy_consent_version' => PrivacyPolicy::VERSION,
                'privacy_consent_at'      => now(),
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
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to submit your application. Please try again.'
            ], 500);
        }
    }
}
