<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\SetupController;

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
| User Booking Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // Booking form page
    Route::get('/booking', function () {
        return view('booking');
    })->name('booking');

    // Store booking
    Route::post('/booking', [BookingController::class, 'store'])
        ->name('bookings.store');

    // Booking history (AJAX)
    Route::get('/booking/history', [BookingController::class, 'history'])
        ->name('bookings.history');
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
| Admin / Posts (KEEP)
| Admin Appointments / Bookings
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->group(function () {

    // Appointments table
    Route::get('/appointments', [BookingController::class, 'adminIndex'])
        ->name('appointments.index');

    Route::delete('/appointments/{id}', [BookingController::class, 'destroy'])->name('appointments.destroy');

    // Reserve / approve booking
    Route::post('/appointments/{booking}/reserve', [BookingController::class, 'reserve'])
        ->name('appointments.reserve');

    // Update booking status (optional future use)
    Route::put('/appointments/{booking}/status', [BookingController::class, 'updateStatus'])
        ->name('appointments.updateStatus');
});

/*
|--------------------------------------------------------------------------
| Posts (KEEP)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('posts', PostController::class);
});

Route::middleware(['auth', 'permission:create posts'])->group(function () {
    Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
});

Route::middleware('auth')->get('/posts', [PostController::class, 'index'])->name('posts.index');
Route::get('/posts/{id}/edit', [PostController::class, 'edit'])->name('posts.edit');

/*
|--------------------------------------------------------------------------
| Sidebar Pages
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    Route::get('/customers', fn () => view('customers'))
        ->name('customers.index');

    Route::get('/staff', fn () => view('staff'))
        ->name('staff.index');

    Route::get('/services', fn () => view('services'))
        ->name('services');

    Route::get('/reports', fn () => view('reports'))
        ->name('reports.index');

    Route::get('/insights', fn () => view('insights'))
        ->name('insights.index');

    Route::get('/more', fn () => view('more'))
        ->name('more.index');
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

/*
|--------------------------------------------------------------------------
| Auth Routes (Laravel Breeze / UI)
|--------------------------------------------------------------------------
*/
require __DIR__ . '/auth.php';
