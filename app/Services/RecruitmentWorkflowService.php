<?php

namespace App\Services;

use App\Mail\StaffCredentialsMail;
use App\Models\Applicant;
use App\Models\Interview;
use App\Models\Staff;
use App\Models\User;
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
        return $user->hasRole('owner') || $user->hasRole('hr');
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
        string $reason
    ): void {
        $this->assertInterviewAccessible($user, $interview);

        DB::transaction(function () use ($user, $interview, $reason) {
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
                'status' => Interview::STATUS_REJECTED,
                'rejection_reason' => trim($reason),
            ]);

            $interview->applicant->update([
                'status' => Applicant::STATUS_REJECTED,
            ]);
        });
    }

    public function hireApplicant(User $actor, Interview $interview): User
    {
        $this->assertInterviewAccessible($actor, $interview);

        return DB::transaction(function () use ($actor, $interview) {
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

            if (User::where('email', $applicant->email)->exists()) {
                throw ValidationException::withMessages([
                    'staff' => 'A user account with this email already exists.',
                ]);
            }

            $tempPassword = Str::random(12);

            $user = User::create([
                'name' => $applicant->full_name,
                'email' => $applicant->email,
                'password' => Hash::make($tempPassword),
                'spa_id' => $applicant->spa_id,
                'branch_id' => $applicant->branch_id,
                'temp_password' => $tempPassword,
                'password_reset_required' => true,
            ]);

            $user->assignRole($role);
            $user->markEmailAsVerified();

            Staff::create([
                'user_id' => $user->id,
                'applicant_id' => $applicant->id,
                'spa_id' => $applicant->spa_id,
                'branch_id' => $applicant->branch_id,
                'employment_status' => 'active',
                'hire_date' => now(),
            ]);

            $interview->update([
                'staff_account_created' => true,
            ]);

            $applicant->update([
                'status' => Applicant::STATUS_HIRED,
            ]);

            DB::afterCommit(function () use ($user, $tempPassword) {
                try {
                    Mail::to($user->email)
                        ->send(new StaffCredentialsMail($user, $tempPassword));
                } catch (\Throwable $e) {
                    report($e);
                }
            });

            return $user;
        });
    }
}
