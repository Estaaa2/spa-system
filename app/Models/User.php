<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\Identity\IdentityVerificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\VerifyEmailWithOtp;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes,HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'email',
        'password',
        'spa_id',
        'branch_id',
        'is_owner',
        'temp_password',
        'password_reset_required',
        'address',   // For customers lang.
        'latitude',  // For customers lang.
        'longitude', // For customers lang.
        'phone',     // For customers lang.
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_otp_hash',
        'profile_photo_path',   // private-disk path; use profilePhotoUrl()
    ];

    /** Fields that make up the legal name an admin verified against the ID. */
    public const LEGAL_NAME_FIELDS = ['first_name', 'middle_name', 'last_name', 'suffix'];

    /**
     * Set while saving when the legal name changed; read by the "updated" hook.
     * Not an attribute (never saved or serialized).
     */
    public bool $identityNameChanged = false;

    /** Set with $identityNameChanged when the name change ended a verification. */
    public bool $identityLostByNameChange = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_owner' => 'boolean',
            'password_reset_required' => 'boolean',
            'email_verification_otp_expires_at' => 'datetime',
            'email_verification_otp_sent_at' => 'datetime',
            'email_verification_otp_attempts' => 'integer',
            'profile_photo_updated_at' => 'datetime',
            'identity_verified_at' => 'datetime',
            'name_changed_at' => 'datetime',
        ];
    }

    /** Customers may change their name once every this many days (UI rework Phase 5). */
    public const NAME_CHANGE_DAYS = 30;

    /** After a change, this many hours to fix a typo without starting the 30 days again. */
    public const NAME_CORRECTION_HOURS = 24;

    /** Until when a name change right after the last one still counts as a correction. */
    public function nameCorrectionUntil(): ?\Illuminate\Support\Carbon
    {
        return $this->name_changed_at?->copy()->addHours(self::NAME_CORRECTION_HOURS);
    }

    /**
     * The moment the name can be changed again, or null when it can be changed
     * now (never changed, still in the correction window, or 30 days have passed).
     * Checked when a name is saved; nothing runs on a schedule.
     */
    public function nameLockedUntil(): ?\Illuminate\Support\Carbon
    {
        if (! $this->name_changed_at) {
            return null;
        }
        if (now()->lt($this->nameCorrectionUntil())) {
            return null;
        }
        $until = $this->name_changed_at->copy()->addDays(self::NAME_CHANGE_DAYS);

        return now()->lt($until) ? $until : null;
    }

    /** Whether these names differ from the saved ones (ignoring spaces at the ends). */
    public function nameDiffersFrom(?string $first, ?string $middle, ?string $last, ?string $suffix = null): bool
    {
        $norm = fn ($v) => trim((string) $v);

        return $norm($first) !== $norm($this->first_name)
            || $norm($middle) !== $norm($this->middle_name)
            || $norm($last) !== $norm($this->last_name)
            || $norm($suffix) !== $norm($this->suffix);
    }

    /**
     * Applies the 30-day rule to a customer's name change. Returns an error
     * message when it's refused; otherwise sets name_changed_at on the model
     * (a correction inside the 24-hour window keeps the original time, so
     * corrections can't push the 30 days back). The caller saves.
     */
    public function guardNameChange(?string $first, ?string $middle, ?string $last, ?string $suffix = null): ?string
    {
        if (! $this->nameDiffersFrom($first, $middle, $last, $suffix)) {
            return null;
        }
        if ($until = $this->nameLockedUntil()) {
            return 'You can change your name once every '.self::NAME_CHANGE_DAYS.' days. You can change it again on '
                .$until->timezone(config('app.timezone'))->format('M j, Y').'.';
        }
        $inCorrection = $this->name_changed_at && now()->lt($this->nameCorrectionUntil());
        if (! $inCorrection) {
            $this->name_changed_at = now();
        }

        return null;
    }

    /**
     * A change to the legal name clears verified status (decision Q9): the admin
     * approved a specific name against the ID. Covers every route that edits a
     * name (staff edit by the owner, /api/me, customer profile, tinker).
     */
    protected static function booted(): void
    {
        static::updating(function (User $user) {
            if (! $user->isDirty(self::LEGAL_NAME_FIELDS)) {
                return;
            }

            $user->identityNameChanged = true;

            if ($user->getOriginal('identity_verified_at') !== null) {
                $user->identity_verified_at = null;
                $user->identityLostByNameChange = true;
            }
        });

        static::updated(function (User $user) {
            if (! $user->identityNameChanged) {
                return;
            }

            $lost = $user->identityLostByNameChange;
            $user->identityNameChanged = false;
            $user->identityLostByNameChange = false;

            app(IdentityVerificationService::class)->supersedeAfterNameChange($user, $lost);
        });
    }

    /**
     * Virtual "name" accessor so all existing code using $user->name
     * continues to work without any changes.
     */
    public function getNameAttribute(): string
    {
        return collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ])
            ->filter()
            ->implode(' ');
    }

    /**
     * Full name — same as name but explicit for readability when needed.
     */
    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    public function spa(): BelongsTo
    {
        return $this->belongsTo(Spa::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function branches()
    {
        // If the user is an owner, get all branches of their owned spas
        return $this->hasManyThrough(
            Branch::class,   // The model you want to get
            Spa::class,      // The intermediate model
            'owner_id',      // Foreign key on Spa table linking to User (owner)
            'spa_id',        // Foreign key on Branch table linking to Spa
            'id',            // Local key on User table
            'id'             // Local key on Spa table
        );
    }

    public function ownedSpas(): HasMany
    {
        return $this->hasMany(Spa::class, 'owner_id');
    }

    public function assignedBookings()
    {
        return $this->hasMany(\App\Models\Booking::class, 'therapist_id');
    }


    public function staff()
    {
        return $this->hasOne(\App\Models\Staff::class, 'user_id');
    }

    // ── Identity verification & profile photo ────────────────────────────────

    public function identityVerifications(): HasMany
    {
        return $this->hasMany(IdentityVerification::class);
    }

    public function latestIdentityVerification(): HasOne
    {
        return $this->hasOne(IdentityVerification::class)->latestOfMany();
    }

    /**
     * In the pool of people who can be assigned treatments (decision Q1): the
     * therapist role, or any staff member the owner marked "Performs treatments".
     * Branch, spa and employment status are checked by the caller.
     */
    public function isTreatmentProvider(): bool
    {
        return $this->hasRole('therapist')
            || (bool) $this->staff?->performs_treatments;
    }

    /**
     * "Can't make it" on an appointment (answer P16): the role permission, or
     * "Performs treatments" on the staff record — the flag already decides who
     * can be assigned, so the same people must be able to hand a booking back.
     * The controller still checks that the booking is assigned to this user.
     */
    public function canRequestReassignment(): bool
    {
        return $this->hasBranchPermission('request appointment reassignment')
            || (bool) $this->staff?->performs_treatments;
    }

    /**
     * Dashboard "My Today" widget (answer P16): the role permission, or
     * "Performs treatments" on the staff record.
     */
    public function seesMyTodayWidget(): bool
    {
        return $this->hasBranchPermission('view dashboard my today')
            || (bool) $this->staff?->performs_treatments;
    }

    /**
     * Employee self-service (sidebar & RBAC rework Phase 6.3): the active staff
     * record that clocks in, sees its own attendance and files its own leave.
     * Same rule as AttendanceController::myStaffRecord() and the nightly
     * attendance:finalize-day job (which marks every active staff member
     * without a record absent), so whoever the job can mark absent can also
     * clock in. Not a role permission: an owner can't switch it off.
     */
    public function activeStaffRecord(): ?Staff
    {
        if (! $this->spa_id) {
            return null;
        }

        return $this->staff
            && (int) $this->staff->spa_id === (int) $this->spa_id
            && $this->staff->employment_status === 'active'
                ? $this->staff
                : null;
    }

    public function hasAttendanceSelfService(): bool
    {
        return $this->activeStaffRecord() !== null;
    }

    /** Query form of isTreatmentProvider(). */
    public function scopeTreatmentProviders(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereHas('roles', fn ($r) => $r->where('name', 'therapist'))
                ->orWhereHas('staff', fn ($s) => $s->where('performs_treatments', true));
        });
    }

    /** Approved and nothing has changed since (photo, legal name, consent). */
    public function isIdentityVerified(): bool
    {
        return $this->identity_verified_at !== null;
    }

    public function scopeIdentityVerified(Builder $query): Builder
    {
        return $query->whereNotNull('identity_verified_at');
    }

    public function hasProfilePhoto(): bool
    {
        return $this->profile_photo_path !== null;
    }

    /**
     * Authorized URL of the profile photo, or null (show initials instead).
     * The "v" parameter changes with every new photo so caches never show an old one.
     */
    public function profilePhotoUrl(): ?string
    {
        if (! $this->hasProfilePhoto()) {
            return null;
        }

        return route('profile-photos.show', [
            'user' => $this->id,
            'v'    => $this->profile_photo_updated_at?->timestamp ?? 0,
        ]);
    }

    /**
     * Same photo for the mobile app (Sanctum token, same IdentityPolicy::viewPhoto
     * rule). Load it with the Authorization header.
     */
    public function profilePhotoApiUrl(): ?string
    {
        if (! $this->hasProfilePhoto()) {
            return null;
        }

        return route('api.profile-photos.show', [
            'user' => $this->id,
            'v'    => $this->profile_photo_updated_at?->timestamp ?? 0,
        ]);
    }

    /** One or two letters for the avatar fallback. */
    public function initials(): string
    {
        $letters = collect([$this->first_name, $this->last_name])
            ->filter()
            ->map(fn ($part) => mb_strtoupper(mb_substr(trim($part), 0, 1)))
            ->implode('');

        return $letters !== '' ? $letters : '?';
    }

    /** First, middle, last name and suffix as shown on an ID. */
    public function legalName(): string
    {
        return $this->name;
    }

    public function requestedPurchaseRequests()
    {
        return $this->hasMany(PurchaseRequest::class, 'requested_by');
    }

    public function reviewedPurchaseRequests()
    {
        return $this->hasMany(PurchaseRequest::class, 'reviewed_by');
    }

    // ── Branch scope ─────────────────────────────────────────────────────────

    /** Owner, HR Head or Finance Head: works across every branch of the spa. */
    public function isSpaWide(): bool
    {
        return $this->hasAnyRole(\App\Services\Payroll\PayrollPermissions::SPA_WIDE_ROLES);
    }

    /** HR Head or Finance Head (central roles that are not the owner). */
    public function isHead(): bool
    {
        return $this->hasAnyRole(\App\Services\Payroll\PayrollPermissions::HEAD_ROLES);
    }

    /** Branch switcher: the owner and the central roles. */
    public function canSwitchBranches(): bool
    {
        return $this->isSpaWide();
    }

    /**
     * The branch this user's payroll view is limited to: null for spa-wide roles
     * (every branch), otherwise the home branch. A non-spa-wide account with no
     * branch gets 0, which matches nothing (never "every branch").
     */
    public function payrollBranchScope(): ?int
    {
        return $this->isSpaWide() ? null : (int) ($this->branch_id ?? 0);
    }

    public function currentBranchId(): ?int
    {
        // Lookups are memoized per request (BranchPermissionCache, Phase 2 F3).
        $cache = app(\App\Services\Permissions\BranchPermissionCache::class);

        if ($this->isHead()) {
            $sessionBranchId = session('current_branch_id');

            if ($sessionBranchId && $this->spa_id && $cache->branchBelongsToSpa((int) $sessionBranchId, (int) $this->spa_id)) {
                return (int) $sessionBranchId;
            }

            // A head starts on their home branch (where they report), not the main branch.
            if ($this->branch_id) {
                session(['current_branch_id' => $this->branch_id]);
            }

            return $this->branch_id;
        }

        if ($this->hasRole('owner')) {
            $sessionBranchId = session('current_branch_id');

            if ($sessionBranchId && $this->spa_id) {
                // Validate the session branch actually belongs to this spa
                if ($cache->branchBelongsToSpa((int) $sessionBranchId, (int) $this->spa_id)) {
                    return (int) $sessionBranchId;
                }
            }

            // Fallback: auto-select the main branch (or the first one).
            $branchId = $this->spa_id ? $cache->mainBranchId((int) $this->spa_id) : null;

            if ($branchId) {
                session(['current_branch_id' => $branchId]);
                return $branchId;
            }

            return null;
        }

        // Managers and all other staff use their assigned branch
        return $this->branch_id;
    }

    public function hasBranchPermission(string $permission): bool
    {
        $branchId = $this->permissionBranchId($permission);

        if (!$branchId) {
            return $this->hasPermissionTo($permission); // fallback to global
        }

        // One query per (branch, role) per request instead of one per check (Phase 2 F3).
        $overrides = app(\App\Services\Permissions\BranchPermissionCache::class)
            ->overrides((int) $branchId, $this->getRoleNames()->first());

        if (array_key_exists($permission, $overrides)) {
            return $overrides[$permission]; // branch override takes precedence
        }

        return $this->hasPermissionTo($permission); // fall back to global
    }

    /**
     * Which branch's Roles & Permissions overrides decide this permission.
     *
     * - HR Head / Finance Head: every permission is spa-wide, so it is read from the
     *   main branch (Roles & Permissions stores their changes there).
     * - Owner: payroll permissions are read from the main branch (payroll is
     *   spa-wide); everything else keeps following the branch being viewed.
     * - Everyone else: their current (home) branch, unchanged.
     */
    public function permissionBranchId(string $permission): ?int
    {
        $spaWideHere = $this->isHead()
            || ($this->hasRole('owner') && \App\Services\Payroll\PayrollPermissions::isPayrollPermission($permission));

        if ($spaWideHere && $this->spa_id) {
            $main = app(\App\Services\Permissions\BranchPermissionCache::class)->mainBranchId((int) $this->spa_id);

            if ($main) {
                return $main;
            }
        }

        return $this->currentBranchId();
    }

    public function generateEmailVerificationOtp(): string
    {
        $otp = str_pad(
            (string) random_int(0, 999999),
            6,
            '0',
            STR_PAD_LEFT
        );

        $this->forceFill([
            'email_verification_otp_hash' => Hash::make($otp),
            'email_verification_otp_expires_at' => now()->addMinutes(10),
            'email_verification_otp_sent_at' => now(),
            'email_verification_otp_attempts' => 0,
        ])->saveQuietly();

        return $otp;
    }

    public function clearEmailVerificationOtp(): void
    {
        $this->forceFill([
            'email_verification_otp_hash' => null,
            'email_verification_otp_expires_at' => null,
            'email_verification_otp_sent_at' => null,
            'email_verification_otp_attempts' => 0,
        ])->saveQuietly();
    }

    public function markEmailAsVerified(): bool
    {
        if ($this->hasVerifiedEmail()) {
            return false;
        }

        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
            'email_verification_otp_hash' => null,
            'email_verification_otp_expires_at' => null,
            'email_verification_otp_sent_at' => null,
            'email_verification_otp_attempts' => 0,
        ])->save();
    }

    public function sendEmailVerificationNotification(): void
    {
        $otp = $this->generateEmailVerificationOtp();

        $this->notify(new VerifyEmailWithOtp($otp));
    }

}
