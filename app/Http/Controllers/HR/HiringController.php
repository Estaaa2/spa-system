<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Services\RecruitmentWorkflowService;

class HiringController extends Controller
{

    public function __construct(
        private RecruitmentWorkflowService $recruitment
    ) {
    }
    private function getSpaAndBranch()
    {
        $user = Auth::user();
        $spa = $user->spa;
        $branchId = $user->currentBranchId();

        return [$spa, $branchId];
    }

    public function index()
    {
        // Open postings of this branch the walk-in can be recorded against (Phase 5, Q8 A).
        $openings = $this->recruitment->postingsFor(Auth::user())
            ->open()
            ->orderBy('title')
            ->get();

        return view('hr.hiring.index', [
            'openings' => $openings,
            'branchHours' => app(\App\Services\Staff\WorkScheduleResolver::class)
                ->branchHours((int) Auth::user()->currentBranchId()),
        ]);
    }

    public function store(Request $request)
    {
        [$spa, $branchId] = $this->getSpaAndBranch();

        abort_unless($spa && $branchId, 403);

        // Same fields as the public form (Phase 5, Q2 A / Q3 A): no date of birth
        // (RA 10911), no gender or civil status (RA 10173, only what is needed).
        $validated = $request->validateWithBag('application', [
            'job_posting_id' => [
                'nullable',
                'integer',
                Rule::exists('job_postings', 'id')->where(fn ($q) => $q
                    ->where('spa_id', $spa->id)
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at')),
            ],
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|regex:/^09\d{9}$/',
            'position_applied' => 'required_without:job_posting_id|nullable|in:therapist,receptionist,manager,hr,finance',
            'availability' => 'nullable|in:full_time,part_time,weekdays,weekends,shifting,flexible',
            'schedule_fit' => 'required_with:job_posting_id|nullable|boolean',
            'schedule_fit_note' => 'nullable|string|max:255',
            'address' => 'required|string|max:255',
            'education' => 'nullable|in:high_school,vocational,undergraduate,college,postgrad',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_relation' => 'nullable|string|max:255',
            'emergency_contact_phone' => [
                'nullable',
                'regex:/^09\d{9}$/',
            ],
            'expected_start_date' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
        ], [
            'position_applied.required_without' => 'Pick the position, or pick a job opening.',
            'schedule_fit.required_with' => 'Say whether the applicant can work this opening\'s schedule.',
            'job_posting_id.exists' => 'That job opening is not in this branch.',
        ]);

        if (! empty($validated['job_posting_id'])) {
            $posting = JobPosting::find($validated['job_posting_id']);

            if (! $posting?->isOpen()) {
                return back()->withInput()->withErrors(['job_posting_id' => 'That job opening is closed. Pick another or "No posting".'], 'application');
            }

            // The opening decides the role and the schedule (Phase 5, Q9 A).
            $validated['position_applied'] = $posting->role;
            $validated['availability'] = null;
        } else {
            $validated['job_posting_id'] = null;
            $validated['schedule_fit'] = null;
            $validated['schedule_fit_note'] = null;
        }

        if ($request->hasFile('resume')) {
            $validated['resume_path'] = $request
                ->file('resume')
                ->store('resumes', 'local'); // private disk: personal data
        }

        unset($validated['resume']);

        Applicant::create([
            ...$validated,
            'spa_id' => $spa->id,
            'branch_id' => $branchId,
            'source' => 'manual',
            'status' => Applicant::STATUS_PENDING,
        ]);

        return redirect()
            ->route('applications.index')
            ->with('success', 'Application submitted successfully.'
        );
    }

    public function viewResume(Applicant $applicant)
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $canView =
            ($user->hasBranchPermission('view applications') ?? false) ||
            ($user->hasBranchPermission('edit applications') ?? false);

        abort_unless($canView, 403);

        $this->recruitment->assertApplicantAccessible(
            $user,
            $applicant
        );

        abort_if(
            !$applicant->resume_path,
            404,
            'Resume not found.'
        );

        // Résumés live on the private disk. The public disk is only checked
        // for files uploaded before `php artisan storage:privatize` was run.
        $disk = Storage::disk('local')->exists($applicant->resume_path)
            ? Storage::disk('local')
            : Storage::disk('public');

        abort_unless(
            $disk->exists($applicant->resume_path),
            404,
            'Resume file not found.'
        );

        $absolutePath = $disk->path($applicant->resume_path);

        $extension = strtolower(
            pathinfo(
                $applicant->resume_path,
                PATHINFO_EXTENSION
            )
        );

        $allowedExtensions = [
            'pdf',
            'doc',
            'docx',
        ];

        abort_unless(
            in_array($extension, $allowedExtensions, true),
            404,
            'Unsupported resume file.'
        );

        $mimeType = $disk->mimeType($applicant->resume_path);

        $safeName = preg_replace(
            '/[^A-Za-z0-9\-_]/',
            '_',
            $applicant->full_name
        );

        $filename = $safeName . '_Resume.' . $extension;

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
