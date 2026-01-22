<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'spa_id',
        'branch_id',
        'employment_status',
        'hire_date',
    ];

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship with Branches (if you have branch_staff pivot table)
    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_staff');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // Relationship with Bookings
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'therapist_id');
    }

    public function todaysAppointments()
    {
        return $this->bookings()
            ->whereDate('appointment_date', today())
            ->whereIn('status', ['reserved', 'confirmed']);
    }

    // Scope for active staff
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Get status badge color
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'active' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
            'inactive' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    // Get role badge color
    public function getRoleColorAttribute()
    {
        return match($this->roles) {
            'therapist' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
            'receptionist' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
            'manager' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
            'admin' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        };
    }
}
