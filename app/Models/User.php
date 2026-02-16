<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
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
        if ($this->hasRole('manager')) {
            return $this->branch_id;
        }

        return session('current_branch_id');
    }
}
