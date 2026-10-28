<?php

namespace App\Services\Identity;

use App\Models\IdentityFileView;
use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The only place that changes identity-verification state or the files behind it.
 *
 * Rules (claude/staff-verification-phases.md, D1–D15 + Phase 0 decisions):
 * - Only treatment providers (therapist role or "Performs treatments") submit.
 * - One pending submission per person; resubmit after rejection/withdrawal/supersede.
 * - Verified = users.identity_verified_at is set. A new profile photo, a legal-name
 *   change or a consent withdrawal clears it (D5, Q9, Q11).
 * - ID and selfie files are deleted once the request is decided, withdrawn or
 *   superseded (D9). The row stays as history (Q21).
 * - File paths and image bytes are never logged.
 */
class IdentityVerificationService
{
    public function __construct(private ImageSanitizer $images)
    {
    }

    // ── Status for screens ───────────────────────────────────────────────────

    public const STATE_NOT_STARTED = 'not_started';
    public const STATE_PENDING = 'pending';
    public const STATE_APPROVED = 'approved';
    public const STATE_REJECTED = 'rejected';
    public const STATE_NEEDS_REVERIFICATION = 'needs_reverification';
    public const STATE_WITHDRAWN = 'withdrawn';

    /**
     * One state for the profile page, staff list and API:
     * not_started, pending, approved, rejected, needs_reverification (photo or
     * name changed after approval), withdrawn.
     */
    public function stateFor(User $user): string
    {
        if ($user->isIdentityVerified()) {
            return self::STATE_APPROVED;
        }

        $latest = $user->relationLoaded('latestIdentityVerification')
            ? $user->latestIdentityVerification
            : $user->latestIdentityVerification()->first();

        return match ($latest?->status) {
            null => self::STATE_NOT_STARTED,
            IdentityVerification::STATUS_PENDING => self::STATE_PENDING,
            IdentityVerification::STATUS_REJECTED => self::STATE_REJECTED,
            IdentityVerification::STATUS_WITHDRAWN => self::STATE_WITHDRAWN,
            IdentityVerification::STATUS_SUPERSEDED => self::STATE_NEEDS_REVERIFICATION,
            // Approved row but the user flag is off: treat as needing a new review.
            default => self::STATE_NEEDS_REVERIFICATION,
        };
    }

    // ── Profile photo ────────────────────────────────────────────────────────

    /**
     * Saves a new profile photo. If the person was verified or had a pending
     * request, that approval/request no longer matches the face on file, so it is
     * superseded and they must verify again.
     *
     * @return array{was_verified: bool, pending_superseded: bool}
     */
    public function updateProfilePhoto(User $user, UploadedFile $file): array
    {
        $bytes = $this->images->toJpeg($file, (int) config('identity.output.profile_photo_max_side', 800), 'photo');
        $newPath = $this->writeFile($this->photoDirectory($user), $bytes);

        try {
            $result = DB::transaction(function () use ($user, $newPath) {
                $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $oldPath = $locked->profile_photo_path;
                $wasVerified = $locked->identity_verified_at !== null;

                $pendingSuperseded = $this->supersedeOpenRows(
                    $locked,
                    IdentityVerification::SUPERSEDED_PHOTO_CHANGED
                );

                $locked->forceFill([
                    'profile_photo_path'       => $newPath,
                    'profile_photo_updated_at' => now(),
                    'identity_verified_at'     => null,
                ])->saveQuietly();

                if ($oldPath && $oldPath !== $newPath) {
                    $this->deleteAfterCommit([$oldPath]);
                }

                return ['was_verified' => $wasVerified, 'pending_superseded' => $pendingSuperseded];
            });
        } catch (\Throwable $e) {
            $this->disk()->delete($newPath);
            throw $e;
        }

        $user->refresh();

        return $result;
    }

    /**
     * Removes the profile photo. Refused while the person is verified or waiting
     * for review, because the photo is what the customer checks at the door;
     * they can replace it instead (which starts a new review).
     */
    public function removeProfilePhoto(User $user): void
    {
        DB::transaction(function () use ($user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (! $locked->profile_photo_path) {
                return;
            }

            $hasPending = $locked->identityVerifications()->pending()->exists();

            if ($locked->identity_verified_at !== null || $hasPending) {
                throw ValidationException::withMessages([
                    'photo' => 'Your photo is part of your identity verification, so it cannot be removed. Upload a new photo instead.',
                ]);
            }

            $this->deleteAfterCommit([$locked->profile_photo_path]);

            $locked->forceFill([
                'profile_photo_path'       => null,
                'profile_photo_updated_at' => now(),
            ])->saveQuietly();
        });

        $user->refresh();
    }

    // ── Submission ───────────────────────────────────────────────────────────

    /**
     * Creates a pending request. $channel is "web" or "api" (recorded with consent).
     *
     * @throws ValidationException
     */
    public function submit(User $user, string $idType, UploadedFile $idImage, UploadedFile $selfie, string $channel = 'web'): IdentityVerification
    {
        $user->loadMissing('staff');

        if (! $user->isTreatmentProvider()) {
            throw ValidationException::withMessages([
                'id_type' => 'Only staff who perform treatments need to verify their identity.',
            ]);
        }

        if (! in_array($idType, IdentityVerification::idTypeKeys(), true)) {
            throw ValidationException::withMessages(['id_type' => 'Choose one of the accepted IDs.']);
        }

        $maxSide = (int) config('identity.output.verification_max_side', 1600);
        $idBytes = $this->images->toJpeg($idImage, $maxSide, 'id_image');
        $selfieBytes = $this->images->toJpeg($selfie, $maxSide, 'selfie');

        $directory = $this->verificationDirectory($user);
        $idPath = $this->writeFile($directory, $idBytes, 'id');
        $selfiePath = $this->writeFile($directory, $selfieBytes, 'selfie');

        try {
            return DB::transaction(function () use ($user, $idType, $idPath, $selfiePath, $channel) {
                // Locking the user row serializes two submits sent at the same time.
                $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

                if (! $locked->profile_photo_path) {
                    throw ValidationException::withMessages([
                        'photo' => 'Upload a profile photo first. The admin compares it with your ID and selfie.',
                    ]);
                }

                if ($locked->identity_verified_at !== null) {
                    throw ValidationException::withMessages([
                        'id_type' => 'Your identity is already verified.',
                    ]);
                }

                if ($locked->identityVerifications()->pending()->exists()) {
                    throw ValidationException::withMessages([
                        'id_type' => 'You already have a request waiting for review.',
                    ]);
                }

                $now = now();

                return IdentityVerification::create([
                    'user_id'             => $locked->id,
                    'spa_id'              => $locked->spa_id,
                    'status'              => IdentityVerification::STATUS_PENDING,
                    'id_type'             => $idType,
                    'id_image_path'       => $idPath,
                    'selfie_path'         => $selfiePath,
                    'photo_path_reviewed' => $locked->profile_photo_path,
                    'name_reviewed'       => $locked->legalName(),
                    'consent_version'     => (string) config('identity.consent_version'),
                    'consent_given_at'    => $now,
                    'consent_channel'     => $channel === 'api' ? 'api' : 'web',
                    'submitted_at'        => $now,
                ]);
            });
        } catch (\Throwable $e) {
            $this->disk()->delete([$idPath, $selfiePath]);
            throw $e;
        }
    }

    // ── Admin decision ───────────────────────────────────────────────────────

    /**
     * Approves a pending request and deletes the ID and selfie files. Refused if
     * the photo or legal name changed after submission (the request is then
     * superseded and the person must submit again).
     *
     * @throws ValidationException
     */
    public function approve(IdentityVerification $verification, User $reviewer): IdentityVerification
    {
        // A mismatch closes the request. That write must commit, so the
        // exception is thrown after the transaction instead of inside it.
        $outcome = DB::transaction(function () use ($verification, $reviewer) {
            $row = $this->lockPending($verification, $reviewer);
            $user = User::whereKey($row->user_id)->lockForUpdate()->firstOrFail();

            if ($user->profile_photo_path !== $row->photo_path_reviewed) {
                $this->supersede($row, IdentityVerification::SUPERSEDED_PHOTO_CHANGED);

                return 'The staff member changed their profile photo after submitting. This request was closed; they need to submit again.';
            }

            if ($user->legalName() !== $row->name_reviewed) {
                $this->supersede($row, IdentityVerification::SUPERSEDED_NAME_CHANGED);

                return 'The staff member\'s name changed after submitting. This request was closed; they need to submit again.';
            }

            $now = now();

            $row->forceFill([
                'status'      => IdentityVerification::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $now,
            ])->save();

            $user->forceFill(['identity_verified_at' => $now])->saveQuietly();

            $this->purgeFiles($row);

            Log::info('Identity verification approved.', [
                'verification_id' => $row->id,
                'user_id'         => $row->user_id,
                'reviewer_id'     => $reviewer->id,
            ]);

            return $row;
        });

        if (is_string($outcome)) {
            throw ValidationException::withMessages(['decision' => $outcome]);
        }

        return $outcome;
    }

    /** Rejects a pending request with a reason the staff member will see, and deletes the files. */
    public function reject(IdentityVerification $verification, User $reviewer, string $reason): IdentityVerification
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Tell the staff member why, so they can fix it (at least 5 characters).',
            ]);
        }

        return DB::transaction(function () use ($verification, $reviewer, $reason) {
            $row = $this->lockPending($verification, $reviewer);

            $row->forceFill([
                'status'           => IdentityVerification::STATUS_REJECTED,
                'reviewed_by'      => $reviewer->id,
                'reviewed_at'      => now(),
                'rejection_reason' => mb_substr($reason, 0, 1000),
            ])->save();

            $this->purgeFiles($row);

            Log::info('Identity verification rejected.', [
                'verification_id' => $row->id,
                'user_id'         => $row->user_id,
                'reviewer_id'     => $reviewer->id,
            ]);

            return $row;
        });
    }

    // ── Consent withdrawal (NPC Circular 2023-04) ────────────────────────────

    /**
     * Withdraws consent: a pending request is closed and its files deleted; an
     * approval is ended and verified status is cleared. Returns the row that was
     * withdrawn, or null when there was nothing to withdraw.
     */
    public function withdraw(User $user): ?IdentityVerification
    {
        return DB::transaction(function () use ($user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $row = $locked->identityVerifications()
                ->whereIn('status', [IdentityVerification::STATUS_PENDING, IdentityVerification::STATUS_APPROVED])
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $row) {
                return null;
            }

            $now = now();

            $row->forceFill([
                'status'               => IdentityVerification::STATUS_WITHDRAWN,
                'consent_withdrawn_at' => $now,
            ])->save();

            $this->purgeFiles($row);

            if ($locked->identity_verified_at !== null) {
                $locked->forceFill(['identity_verified_at' => null])->saveQuietly();
            }

            $user->refresh();

            return $row;
        });
    }

    // ── Hooks ────────────────────────────────────────────────────────────────

    /** Called by User::updated() after a legal-name change. */
    public function supersedeAfterNameChange(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->supersedeOpenRows($user, IdentityVerification::SUPERSEDED_NAME_CHANGED);
        });
    }

    /** Audit trail for every time an admin opens a file. */
    public function recordFileView(IdentityVerification $verification, User $viewer, string $file, ?string $ip): void
    {
        IdentityFileView::create([
            'identity_verification_id' => $verification->id,
            'viewer_id'                => $viewer->id,
            'file'                     => $file,
            'ip'                       => $ip,
            'viewed_at'                => now(),
        ]);
    }

    /**
     * Clears the stored paths and deletes the files once the transaction commits.
     * A failed delete leaves an unreferenced file that identity:purge removes later.
     */
    public function purgeFiles(IdentityVerification $row): void
    {
        $paths = array_values(array_filter([$row->id_image_path, $row->selfie_path]));

        if ($paths === [] && $row->files_purged_at !== null) {
            return;
        }

        $row->forceFill([
            'id_image_path'   => null,
            'selfie_path'     => null,
            'files_purged_at' => $row->files_purged_at ?? now(),
        ])->save();

        $this->deleteAfterCommit($paths);
    }

    // ── Internals ────────────────────────────────────────────────────────────

    public function disk(): Filesystem
    {
        return Storage::disk(config('identity.disk', 'local'));
    }

    public function verificationDirectory(User $user): string
    {
        return config('identity.directories.verifications', 'identity-verifications').'/'.$user->id;
    }

    public function photoDirectory(User $user): string
    {
        return config('identity.directories.profile_photos', 'profile-photos').'/'.$user->id;
    }

    /** Re-locks the row and checks it can still be decided by this reviewer. */
    private function lockPending(IdentityVerification $verification, User $reviewer): IdentityVerification
    {
        $row = IdentityVerification::whereKey($verification->id)->lockForUpdate()->firstOrFail();

        if (! $row->isPending()) {
            throw ValidationException::withMessages([
                'decision' => 'This request was already '.$row->status.'.',
            ]);
        }

        if ((int) $row->user_id === (int) $reviewer->id) {
            throw ValidationException::withMessages([
                'decision' => 'You cannot review your own verification.',
            ]);
        }

        return $row;
    }

    /**
     * Closes the pending request and ends any approval for $user.
     * Returns true when a pending request was closed.
     */
    private function supersedeOpenRows(User $user, string $reason): bool
    {
        $rows = IdentityVerification::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [IdentityVerification::STATUS_PENDING, IdentityVerification::STATUS_APPROVED])
            ->lockForUpdate()
            ->get();

        $closedPending = false;

        foreach ($rows as $row) {
            $closedPending = $closedPending || $row->isPending();
            $this->supersede($row, $reason);
        }

        return $closedPending;
    }

    private function supersede(IdentityVerification $row, string $reason): void
    {
        $row->forceFill([
            'status'            => IdentityVerification::STATUS_SUPERSEDED,
            'superseded_reason' => $reason,
        ])->save();

        $this->purgeFiles($row);
    }

    private function writeFile(string $directory, string $bytes, string $suffix = ''): string
    {
        $name = Str::uuid()->toString().($suffix !== '' ? '-'.$suffix : '').'.jpg';
        $path = $directory.'/'.$name;

        if (! $this->disk()->put($path, $bytes)) {
            throw new \RuntimeException('Could not save the uploaded image. Check that storage/app/private is writable by the web server.');
        }

        return $path;
    }

    /** @param  list<string>  $paths */
    private function deleteAfterCommit(array $paths): void
    {
        $paths = array_values(array_filter($paths));

        if ($paths === []) {
            return;
        }

        DB::afterCommit(function () use ($paths) {
            try {
                $this->disk()->delete($paths);
            } catch (\Throwable $e) {
                // identity:purge removes the leftover files later.
                Log::warning('Identity file delete failed; left for identity:purge.', ['count' => count($paths)]);
            }
        });
    }
}
