<?php

namespace App\Services;

use App\Mail\ApplicantRejectedMail;
use App\Mail\StaffCredentialsMail;
use App\Models\Applicant;
use App\Models\Interview;
use App\Models\JobPosting;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\User;
use App\Services\Staff\WorkScheduleWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecruitmentWorkflowService
{
    public function hasSpaWideAccess(User $user): bool
    {
        return $user->hasAnyRole(['owner', 'hr', 'hr_head']);
    }

    /**
     * Job openings live in the branch zone (staff scheduling & hiring Phase 5):
     * everyone works on the branch they have selected.
     */
    public function postingsFor(User $user): Builder
    {
        $branchId = $user->currentBranchId();

        abort_unless($user->spa_id && $branchId, 403, 'No active branch selected.');

        return JobPosting::query()
            ->where('spa_id', $user->spa_id)
            ->where('branch_id', $branchId);
    }

    public function assertPostingAccessible(User $user, JobPosting $posting): void
    {
        abort_unless(
            (int) $posting->spa_id === (int) $user->spa_id
                && (int) $posting->branch_id === (int) $user->currentBranchId(),
            403
        );
    }

    public function applicantsFor(User $user): Builder
    {
        $query = Applicant::query()
            ->where('spa_id', $user->spa_id);

        if (!$this->hasSpaWideAccess($user)) {
            $branchId = $user->currentBranchId();

            abort_unless($branchId, 403, 'No active branch selected.');

            $query->where('branch_id', $branchId);
        }

        return $query;
    }

    public function interviewsFor(User $user): Builder
    {
        $query = Interview::query()
            ->where('spa_id', $user->spa_id);

        if (!$this->hasSpaWideAccess($user)) {
            $branchId = $user->currentBranchId();

            abort_unless($branchId, 403, 'No active branch selected.');

            $query->where('branch_id', $branchId);
        }

        return $query;
    }

    public function assertApplicantAccessible(User $user, Applicant $applicant): void
    {
        abort_unless(
            (int) $applicant->spa_id === (int) $user->spa_id,
            403
        );

        if (!$this->hasSpaWideAccess($user)) {
            abort_unless(
                (int) $applicant->branch_id === (int) $user->currentBranchId(),
                403
            );
        }
    }

    public function assertInterviewAccessible(User $user, Interview $interview): void
    {
        abort_unless(
            (int) $interview->spa_id === (int) $user->spa_id,
            403
        );

        if (!$this->hasSpaWideAccess($user)) {
            abort_unless(
                (int) $interview->branch_id === (int) $user->currentBranchId(),
                403
            );
        }
    }

    public function scheduleInterview(
        User $user,
        Applicant $applicant,
        array $data
    ): Interview {
        $this->assertApplicantAccessible($user, $applicant);

        return DB::transaction(function () use ($user, $applicant, $data) {
            $applicant = Applicant::query()
                ->whereKey($applicant->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertApplicantAccessible($user, $applicant);

            if ($applicant->status !== Applicant::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'interview_date' => 'Only pending applicants can be scheduled for an interview.',
                ]);
            }

            if ($applicant->interview()->exists()) {
                throw ValidationException::withMessages([
                    'interview_date' => 'This applicant already has an interview scheduled.',
                ]);
            }

            $interview = Interview::create([
                'applicant_id' => $applicant->id,
                'spa_id' => $applicant->spa_id,
                'branch_id' => $applicant->branch_id,
                'interviewed_by' => $user->id,
                'interview_date' => $data['interview_date'],
                'interview_time' => $data['interview_time'],
                'remarks' => $data['remarks'] ?? null,
                'status' => Interview::STATUS_PENDING,
            ]);

            $applicant->update([
                'status' => Applicant::STATUS_INTERVIEW,
            ]);

            return $interview;
        });
    }

    public function approveInterview(User $user, Interview $interview): void
    {
        $this->assertInterviewAccessible($user, $interview);

        DB::transaction(function () use ($user, $interview) {
            $interview = Interview::with('applicant')
                ->whereKey($interview->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertInterviewAccessible($user, $interview);

            if ($interview->status !== Interview::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'interview' => 'This interview has already been reviewed.',
                ]);
            }

            if ($interview->applicant->status !== Applicant::STATUS_INTERVIEW) {
                throw ValidationException::withMessages([
                    'interview' => 'The applicant is not currently in the interview stage.',
                ]);
            }

            $interview->update([
                'status' => Interview::STATUS_APPROVED,
                'rejection_reason' => null,
            ]);

            $interview->applicant->update([
                'status' => Applicant::STATUS_APPROVED,
            ]);
        });
    }

    public function rejectInterview(
        User $user,
        Interview $interview,
        string $reason,
        ?string $applicantMessage = null
    ): void {
        $this->assertInterviewAccessible($user, $interview);

        DB::transaction(function () use ($user, $interview, $reason, $applicantMessage) {
            $interview = Interview::with('applicant')
                ->whereKey($interview->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertInterviewAccessible($user, $interview);

            if ($interview->status !== Interview::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'interview' => 'This interview has already been reviewed.',
                ]);
            }

            if ($interview->applicant->status !== Applicant::STATUS_INTERVIEW) {
                throw ValidationException::withMessages([
                    'interview' => 'The applicant is not currently in the interview stage.',
                ]);
            }

            $applicantMessage = filled($applicantMessage) ? trim($applicantMessage) : null;

            $interview->update([
                'status' => Interview::STATUS_REJECTED,
                'rejection_reason' => trim($reason),
                'applicant_message' => $applicantMessage,
            ]);

            $interview->applicant->update([
                'status' => Applicant::STATUS_REJECTED,
            ]);

            // The internal reason never leaves HR; only the optional message does.
            DB::afterCommit(function () use ($interview) {
                $email = $interview->applicant?->email;

                if (! $email) {
                    return;
                }

                try {
                    Mail::to($email)->send(new ApplicantRejectedMail($interview->fresh(['applicant', 'spa'])));
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        });
    }

    /**
     * Creates the staff account for an approved interview, or links the staff
     * account that was already added by hand on the Staff page with the same email.
     *
     * @param  array{first_name?: ?string, middle_name?: ?string, last_name?: ?string}  $name
     * @return array{user: User, linked: bool}
     */
    public function hireApplicant(User $actor, Interview $interview, array $name = []): array
    {
        $this->assertInterviewAccessible($actor, $interview);

        return DB::transaction(function () use ($actor, $interview, $name) {
            $interview = Interview::with('applicant')
                ->whereKey($interview->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertInterviewAccessible($actor, $interview);

            $applicant = $interview->applicant;

            if ($interview->status !== Interview::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'staff' => 'Only approved interviews can proceed to staff creation.',
                ]);
            }

            if ($applicant->status !== Applicant::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'staff' => 'The applicant must be approved before creating a staff account.',
                ]);
            }

            if (
                $interview->staff_account_created ||
                Staff::where('applicant_id', $applicant->id)->exists()
            ) {
                throw ValidationException::withMessages([
                    'staff' => 'A staff account has already been created for this applicant.',
                ]);
            }

            // Account already added on the Staff page with this email: link it
            // instead of failing with "email already exists".
            $existingUser = User::where('email', $applicant->email)->first();

            if ($existingUser) {
                $existingStaff = Staff::where('user_id', $existingUser->id)
                    ->where('spa_id', $applicant->spa_id)
                    ->first();

                if (! $existingStaff || $existingStaff->applicant_id !== null) {
                    throw ValidationException::withMessages([
                        'staff' => 'This email already belongs to another account (for example a customer account or another staff member). Ask the applicant for a different email.',
                    ]);
                }

                $this->markHired($interview, $applicant, $existingStaff);

                return ['user' => $existingUser, 'linked' => true];
            }

            $firstName = trim((string) ($name['first_name'] ?? ''));
            $lastName = trim((string) ($name['last_name'] ?? ''));
            $middleName = trim((string) ($name['middle_name'] ?? ''));

            if ($firstName === '' || $lastName === '') {
                throw ValidationException::withMessages([
                    'staff' => 'First name and last name are required to create the staff account.',
                ]);
            }

            $role = $applicant->position_applied;

            if (!in_array($role, [
                'therapist',
                'receptionist',
                'manager',
                'hr',
                'finance',
            ], true)) {
                throw ValidationException::withMessages([
                    'staff' => 'The applicant does not have a valid staff position.',
                ]);
            }

            // Same plan rules as adding staff by hand (StaffController::store).
            $spa = Spa::find($applicant->spa_id);

            if (
                in_array($role, ['hr', 'finance'], true)
                && ! ($spa?->hasFeature('manpower') ?? false)
            ) {
                throw ValidationException::withMessages([
                    'staff' => 'HR and Finance accounts require the Premium plan.',
                ]);
            }

            if (! ($spa?->canAddStaff() ?? false)) {
                $limit = $spa?->planLimit('staff') ?? 0;

                throw ValidationException::withMessages([
                    'staff' => "Your plan allows {$limit} active staff members. Upgrade or free a slot before hiring.",
                ]);
            }

            $tempPassword = Str::random(12);

            // users has first/middle/last name columns; there is no "name" column.
            $user = User::create([
                'first_name' => $firstName,
                'middle_name' => $middleName !== '' ? $middleName : null,
                'last_name' => $lastName,
                'email' => $applicant->email,
                'password' => Hash::make($tempPassword),
                'spa_id' => $applicant->spa_id,
                'branch_id' => $applicant->branch_id,
                'temp_password' => $tempPassword,
                'password_reset_required' => true,
            ]);

            $user->assignRole($role);
            $user->markEmailAsVerified();

            $staff = Staff::create([
                'user_id' => $user->id,
                'spa_id' => $applicant->spa_id,
                'branch_id' => $applicant->branch_id,
                'employment_status' => 'active',
                'hire_date' => now(),
            ]);

            $this->markHired($interview, $applicant, $staff);

            // Hired from a job opening: its weekly schedule becomes the new staff
            // member's work schedule from today; HR can edit it (Phase 5, Q10 A).
            $posting = $applicant->jobPosting;
            if ($posting && is_array($posting->schedule) && $posting->schedule !== []) {
                app(WorkScheduleWriter::class)->savePattern(
                    $staff,
                    now()->toDateString(),
                    self::patternFromPosting($posting->schedule),
                    $actor->id
                );
            }

            DB::afterCommit(function () use ($user, $tempPassword) {
                try {
                    Mail::to($user->email)
                        ->send(new StaffCredentialsMail($user, $tempPassword));
                } catch (\Throwable $e) {
                    report($e);
                }
            });

            return ['user' => $user, 'linked' => false];
        });
    }

    /**
     * Staff added by hand on the Staff page: if an approved applicant of the same
     * spa has this email and no staff account yet, link them so the Interviews
     * page shows "Account Created" instead of offering a second account.
     */
    public function linkStaffToApprovedApplicant(Staff $staff): void
    {
        $email = $staff->user?->email;

        if (! $email || $staff->applicant_id !== null) {
            return;
        }

        $applicant = Applicant::query()
            ->where('spa_id', $staff->spa_id)
            ->where('email', $email)
            ->where('status', Applicant::STATUS_APPROVED)
            ->whereDoesntHave('staff')
            ->whereHas('interview', fn ($q) => $q
                ->where('status', Interview::STATUS_APPROVED)
                ->where('staff_account_created', false))
            ->latest()
            ->first();

        if (! $applicant) {
            return;
        }

        $this->markHired($applicant->interview, $applicant, $staff);
    }

    private function markHired(Interview $interview, Applicant $applicant, Staff $staff): void
    {
        $staff->update(['applicant_id' => $applicant->id]);
        $interview->update(['staff_account_created' => true]);
        $applicant->update(['status' => Applicant::STATUS_HIRED]);

        // Closes the opening when the hires reach its number of openings (Phase 5, Q6 A).
        $applicant->jobPosting?->closeIfFilled();
    }

    /**
     * A posting's schedule in the shape WorkScheduleWriter::savePattern takes.
     *
     * @param array<string, array<string, mixed>> $schedule
     * @return array<string, array{mode: string, start?: string, end?: string}>
     */
    public static function patternFromPosting(array $schedule): array
    {
        $out = [];
        foreach (\App\Models\StaffWorkSchedule::WEEKDAYS as $day) {
            $d = $schedule[$day] ?? [];
            $mode = in_array($d['mode'] ?? null, ['rest', 'branch', 'custom'], true) ? $d['mode'] : 'branch';
            $out[$day] = $mode === 'custom' && ! empty($d['start']) && ! empty($d['end'])
                ? ['mode' => 'custom', 'start' => substr((string) $d['start'], 0, 5), 'end' => substr((string) $d['end'], 0, 5)]
                : ['mode' => $mode === 'custom' ? 'branch' : $mode];
        }

        return $out;
    }
}
