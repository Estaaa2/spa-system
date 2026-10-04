<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
        return view('hr.hiring.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validateWithBag('application', [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|regex:/^09\d{9}$/',
            'position_applied' => 'required|in:therapist,receptionist,manager,hr,finance',
            'availability' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date|before:today',
            'civil_status' => 'nullable|string|max:255',
            'address' => 'required|string|max:255',
            'education' => 'nullable|string|max:255',
            'resume' => 'nullable|file|mimes:pdf|max:5120',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_relation' => 'nullable|string|max:255',
            'emergency_contact_phone' => [
                'nullable',
                'regex:/^09\d{9}$/',
            ],
            'expected_start_date' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
        ]);

        [$spa, $branchId] = $this->getSpaAndBranch();

        abort_unless($spa && $branchId, 403);

        if ($request->hasFile('resume')) {
            $validated['resume_path'] = $request
                ->file('resume')
                ->store('resumes', 'public');
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

        abort_unless(
            Storage::disk('public')->exists($applicant->resume_path),
            404,
            'Resume file not found.'
        );

        $absolutePath = Storage::disk('public')
            ->path($applicant->resume_path);

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

        $mimeType = Storage::disk('public')
            ->mimeType($applicant->resume_path);

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

    public function update(Request $request, JobPosting $posting)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,closed,draft',
        ]);

        $posting->update($validated);

        return back()->with(
            'success',
            'Job posting updated.'
        );
    }

    public function destroy(JobPosting $posting)
    {
        $posting->delete();

        return back()->with(
            'success',
            'Job posting deleted.'
        );
    }
}
