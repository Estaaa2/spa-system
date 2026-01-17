<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\BookingController; // Add this line

/*
|--------------------------------------------------------------------------
| Public
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
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Admin / Posts (KEEP)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('posts', PostController::class);
});

Route::middleware(['auth', 'permission:create posts'])->group(function () {
    Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
});

Route::middleware(['auth'])->get('/posts', [PostController::class, 'index'])->name('posts.index');
Route::get('/posts/{id}/edit', [PostController::class, 'edit'])->name('posts.edit');

Route::get('/bookings', [BookingController::class, 'index']);
Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');

/*
|--------------------------------------------------------------------------
| Bookings
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    // Book an Appointment - View form
    Route::get('/booking', function () {
        return view('booking');
    })->name('booking');

    // Handle form submission
    Route::post('/booking', function () {
        // For now, just redirect back with success message
        // You can add your booking logic here later
        return redirect()->route('booking')
            ->with('success', 'Booking submitted successfully!');
    })->name('bookings.store');
});

/*
|--------------------------------------------------------------------------
| Other Sidebar Pages
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    // Appointments
    Route::get('/appointments', function () {
        return view('appointments');
    })->name('appointments.index');

    // Customers
    Route::get('/customers', function () {
        return view('customers');
    })->name('customers.index');

    Route::get('/staff', function () {
        return view('staff');
    })->name('staff.index');

    // Services (KEEP)
    Route::get('/services', function () {
        return view('services');
    })->name('services');

    // Reports (KEEP)
    Route::get('/reports', function () {
        return view('reports');
    })->name('reports.index');

    // Insights
    Route::get('/insights', function () {
        return view('insights');
    })->name('insights.index');

    Route::get('/more', function () {
        return view('more');
    })->name('more.index');
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

require __DIR__.'/auth.php';
