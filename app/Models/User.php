<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'password',
        'spa_id',
        'branch_id',
        'is_owner',
        'temp_password',
        'password_reset_required',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
        ];
    }

    /**
     * Virtual "name" accessor so all existing code using $user->name
     * continues to work without any changes.
     */
    public function getNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])
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
    
    public function currentBranchId(): ?int
    {
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

    // In User.php — add this method
    public function hasBranchPermission(string $permission): bool
    {
        $branchId = $this->currentBranchId();

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
}
