<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\PackageController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Operations Section
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    // Booking form page
    Route::get('/booking', [BookingController::class, 'create'])
        ->name('booking');

    // Store booking
    Route::post('/booking', [BookingController::class, 'store'])
        ->name('bookings.store');

    // Booking history (AJAX)
    Route::get('/booking/history', [BookingController::class, 'history'])
        ->name('bookings.history');

    // Schedule
    Route::get('/schedule', function () {
        return view('schedule');
    })->name('schedule.index');

    // Staff Availability
    Route::get('/staff-availability', function () {
        return view('staff-availability');
    })->name('staff-availability.index');
});

/*
|--------------------------------------------------------------------------
| Management Section
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::resource('treatments', TreatmentController::class)
    ->except(['index', 'show']); // We don't need separate index page

// Packages CRUD
Route::resource('packages', PackageController::class)
    ->except(['index', 'show']); // We don't need separate index page

// Services main page
Route::get('/services', [ServiceController::class, 'index'])->name('services.index');

    // Staff
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

    // Branches
    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
    Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
});

/*
|--------------------------------------------------------------------------
| Insights Section
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:owner,admin'])->group(function () {
    // Decision Support
    Route::get('/decision-support', function () {
        return view('decision-support');
    })->name('decision-support.index');

    // Reports
    Route::get('/reports', function () {
        return view('reports');
    })->name('reports.index');
});

/*
|--------------------------------------------------------------------------
| Administration Section
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:owner'])->group(function () {
    // Users
    Route::get('/users', function () {
        return view('users');
    })->name('users.index');

    // Roles & Permissions
    Route::get('/roles-permissions', function () {
        return view('roles-permissions');
    })->name('roles-permissions.index');

    // Settings
    Route::get('/settings', function () {
        return view('settings');
    })->name('settings.index');

    // Appointments management
    Route::get('/appointments', [BookingController::class, 'adminIndex'])
        ->name('appointments.index');

    Route::delete('/appointments/{id}', [BookingController::class, 'destroy'])->name('appointments.destroy');

    Route::post('/appointments/{booking}/reserve', [BookingController::class, 'reserve'])
        ->name('appointments.reserve');

    Route::put('/appointments/{booking}/status', [BookingController::class, 'updateStatus'])
        ->name('appointments.updateStatus');

    Route::put('/appointments/{booking}', [BookingController::class, 'update'])
    ->name('appointments.update');

    Route::get('/appointments/{booking}/edit', [BookingController::class, 'edit'])
        ->name('appointments.edit');
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
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
