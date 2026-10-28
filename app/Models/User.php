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
        ];
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
            }
        });

        static::updated(function (User $user) {
            if (! $user->identityNameChanged) {
                return;
            }

            $user->identityNameChanged = false;

            app(IdentityVerificationService::class)->supersedeAfterNameChange($user);
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
        if ($this->isHead()) {
            $sessionBranchId = session('current_branch_id');

            if ($sessionBranchId && Branch::where('id', $sessionBranchId)->where('spa_id', $this->spa_id)->exists()) {
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

            if ($sessionBranchId) {
                // Validate the session branch actually belongs to this spa
                $valid = Branch::where('id', $sessionBranchId)
                    ->where('spa_id', $this->spa_id)
                    ->exists();

                if ($valid) {
                    return $sessionBranchId;
                }
            }

            // Fallback: auto-select main branch (same logic as getCurrentBranch())
            $branch = Branch::where('spa_id', $this->spa_id)
                ->where('is_main', true)
                ->first()
                ?? Branch::where('spa_id', $this->spa_id)->first();

            if ($branch) {
                session(['current_branch_id' => $branch->id]);
                return $branch->id;
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

        $override = \App\Models\BranchRolePermission::where('branch_id', $branchId)
            ->where('role_name', $this->getRoleNames()->first())
            ->where('permission_name', $permission)
            ->first();

        if ($override) {
            return $override->granted; // branch override takes precedence
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
            $main = Branch::where('spa_id', $this->spa_id)->orderByDesc('is_main')->orderBy('id')->value('id');

            if ($main) {
                return (int) $main;
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
