<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\StaffAvailabilityController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\RolePermissionController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('welcome');
})->name('landing.page');

/*
|--------------------------------------------------------------------------
| Dashboard (Admin redirect + Owner permission check)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('role:owner|manager|therapist')
        ->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Admin Dashboard (Admin only + permission)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin', 'permission:view admin dashboard'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    });

/*
|--------------------------------------------------------------------------
| Operations: Booking
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:create booking'])->group(function () {
    Route::get('/booking', [BookingController::class, 'create'])->name('booking');
    Route::post('/booking', [BookingController::class, 'store'])->name('bookings.store');
});

/*
|--------------------------------------------------------------------------
| Operations: Booking history (tie to view appointments)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:view appointments'])->group(function () {
    Route::get('/booking/history', [BookingController::class, 'history'])->name('bookings.history');
});

/*
|--------------------------------------------------------------------------
| Schedule Section
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:view schedule'])->group(function () {
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::get('/schedule/data', [ScheduleController::class, 'data'])->name('schedule.data');
});

/*
|--------------------------------------------------------------------------
| Staff Availability Section (view OR manage)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:view staff availability|manage staff availability'])->group(function () {
    Route::get('/staff-availability', [StaffAvailabilityController::class, 'index'])
        ->name('staff.availability');

    Route::post('/staff-availability', [StaffAvailabilityController::class, 'store'])
        ->name('staff.availability.store');
});

/*
|--------------------------------------------------------------------------
| Branch Routes
|--------------------------------------------------------------------------
| - switch/current: need at least view branches
| - index/show: view branches OR manage branches
| - store/update/destroy: manage branches
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:view branches|manage branches'])->group(function () {
    Route::post('/branch/switch', [BranchController::class, 'switch'])->name('branch.switch');
    Route::get('/branch/current', [BranchController::class, 'getCurrentBranch'])->name('branch.current');

    Route::prefix('branches')->group(function () {
        Route::get('/', [BranchController::class, 'index'])->name('branches.index');
        Route::get('/{branch}', [BranchController::class, 'show'])->name('branches.show');
    });
});

Route::middleware(['auth', 'permission:manage branches'])->group(function () {
    Route::prefix('branches')->group(function () {
        Route::post('/', [BranchController::class, 'store'])->name('branches.store');
        Route::put('/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Management: Services / Treatments / Packages
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:view services|manage services'])->group(function () {
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
});

Route::middleware(['auth', 'permission:manage services'])->group(function () {
    Route::resource('treatments', TreatmentController::class)->except(['index']);
    Route::resource('packages', PackageController::class)->except(['index']);
    // Schedule
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    
    // API route: get operating hours for a branch/day
    Route::get('/api/operating-hours/{branch}/{day}', function($branchId, $day) {
        $hours = \App\Models\OperatingHours::where('branch_id', $branchId)
            ->where('day_of_week', $day)
            ->first();

        if (!$hours) return response()->json(['is_closed' => true]);

        return response()->json([
            'is_closed' => $hours->is_closed,
            'opening_time' => $hours->opening_time,
            'closing_time' => $hours->closing_time,
        ]);
    })->name('api.operating-hours');
});

/*
|--------------------------------------------------------------------------
| Management: Staff
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:view staff|manage staff'])->group(function () {
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
});

Route::middleware(['auth', 'permission:manage staff'])->group(function () {
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
});

/*
|--------------------------------------------------------------------------
| Insights
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:view decision support'])->group(function () {
    Route::get('/decision-support', fn () => view('decision-support'))
        ->name('decision-support.index');
});

Route::middleware(['auth', 'permission:view reports'])->group(function () {
    Route::get('/reports', fn () => view('reports'))
        ->name('reports.index');
});

/*
|--------------------------------------------------------------------------
| Appointments (permission-based)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:view appointments'])->group(function () {
    Route::get('/appointments', [BookingController::class, 'adminIndex'])->name('appointments.index');
});

Route::middleware(['auth', 'permission:delete appointments'])->group(function () {
    Route::delete('/appointments/{id}', [BookingController::class, 'destroy'])->name('appointments.destroy');
});

Route::middleware(['auth', 'permission:edit appointments'])->group(function () {
    Route::post('/appointments/{booking}/reserve', [BookingController::class, 'reserve'])->name('appointments.reserve');
    Route::put('/appointments/{booking}/status', [BookingController::class, 'updateStatus'])->name('appointments.updateStatus');
    Route::put('/appointments/{booking}', [BookingController::class, 'update'])->name('appointments.update');
    Route::get('/appointments/{booking}/edit', [BookingController::class, 'edit'])->name('appointments.edit');
});

/*
|--------------------------------------------------------------------------
| API: Operating hours (used by booking)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:create booking'])->group(function () {
    Route::get('/api/operating-hours/{branch}/{day}', function ($branchId, $day) {
        $hours = \App\Models\OperatingHours::where('branch_id', $branchId)
            ->where('day_of_week', $day)
            ->first();

        if (!$hours) return response()->json(['is_closed' => true]);

        return response()->json([
            'is_closed' => $hours->is_closed,
            'opening_time' => $hours->opening_time,
            'closing_time' => $hours->closing_time,
        ]);
    })->name('api.operating-hours');
});

/*
|--------------------------------------------------------------------------
| Administration (Admin only + permission-based)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->group(function () {

    Route::middleware(['permission:manage users'])->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::put('/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.updateRole');
    });

    Route::middleware(['permission:manage roles'])->group(function () {
        Route::get('/roles-permissions', [RolePermissionController::class, 'index'])->name('roles-permissions.index');
        Route::get('/roles-permissions/{role}/edit', [RolePermissionController::class, 'edit'])->name('roles-permissions.edit');
        Route::put('/roles-permissions/{role}', [RolePermissionController::class, 'update'])->name('roles-permissions.update');
    });

    Route::middleware(['permission:manage settings'])->group(function () {
        Route::get('/settings', fn () => view('settings'))->name('settings.index');
    });
});

/*
|--------------------------------------------------------------------------
| Setup Wizard Routes (Owner Only)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'owner-only'])->group(function () {
    Route::get('/setup', [SetupController::class, 'index'])->name('setup.index');
    Route::post('/setup/spa', [SetupController::class, 'storeSpa'])->name('setup.store-spa');

    Route::get('/setup/branches', [SetupController::class, 'branches'])->name('setup.branches');
    Route::post('/setup/branches', [SetupController::class, 'storeBranch'])->name('setup.store-branch');

    Route::get('/setup/branches/{branch}/operating-hours', [SetupController::class, 'operatingHours'])->name('setup.operating-hours');
    Route::put('/setup/branches/{branch}/operating-hours', [SetupController::class, 'updateOperatingHours'])->name('setup.update-operating-hours');

    Route::get('/setup/branches/{branch}/staff', [SetupController::class, 'staff'])->name('setup.staff');
    Route::post('/setup/branches/{branch}/staff', [SetupController::class, 'storeStaff'])->name('setup.store-staff');

    Route::get('/setup/complete', [SetupController::class, 'complete'])->name('setup.complete');
});

/*
|--------------------------------------------------------------------------
| Profile
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
