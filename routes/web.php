<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('posts/create', [PostController::class, 'create'])
    ->middleware(['auth', 'permission:create posts']);

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('posts', PostController::class);
});



Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/about', function () {
    return view('about');
    })->name('about');

    Route::get('/services', function () {
        return view('services');
    })->name('services');

    Route::get('/packages', function () {
        return view('packages');
    })->name('packages');

    Route::get('/contact', function () {
        return view('contact');
    })->name('contact');

    Route::get('/booking', function () {
        return view('booking');
    })->name('booking');

    Route::get('/users', function () {
    return 'Users page';
    })->name('users.index');

    // Orders
    Route::get('/orders', function () {
        return 'Orders page';
    })->name('orders.index');

    // Reports
    Route::get('/reports', function () {
        return 'Reports page';
    })->name('reports.index');

    Route::get('/settings', function () {
        return 'Settings page';
    })->name('settings.index');

    });

require __DIR__.'/auth.php';
