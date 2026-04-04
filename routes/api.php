<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\SpaController;
use App\Http\Controllers\PaymongoWebhookController;
use App\Http\Controllers\Api\FlutterBookingController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\RescheduleRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// CORS preflight
Route::options('/{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, X-Token-Auth, Authorization, Origin, Accept')
        ->header('Access-Control-Allow-Credentials', 'true');
})->where('any', '.*');

// ── Public endpoints (No authentication needed) ───────────────────────────────
Route::post('/paymongo/webhook', [PaymongoWebhookController::class, 'handle']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::get('/operating-hours/{branchId}/{day}', function ($branchId, $day) {
        \Log::info('Operating hours called - Branch: ' . $branchId . ', Day: ' . $day);

        $hours = \App\Models\OperatingHours::where('branch_id', $branchId)
            ->where('day_of_week', $day)
            ->first();

        if (!$hours) {
            \Log::info('No hours found for branch ' . $branchId . ' on ' . $day);
            return response()->json(['is_closed' => true]);
        }

        \Log::info('Hours found: is_closed=' . $hours->is_closed . ', open=' . $hours->opening_time . ', close=' . $hours->closing_time);

        return response()->json([
            'is_closed'    => (bool) $hours->is_closed,
            'opening_time' => $hours->opening_time,
            'closing_time' => $hours->closing_time,
        ]);
    });

// ── Public Spa endpoints (⚠️ ORDER IS CRITICAL - SPECIFIC ROUTES FIRST) ───────
Route::get('/spas/cavite', [SpaController::class, 'cavite']);
Route::get('/featured-spas', [SpaController::class, 'featured']);
Route::get('/spas/other', [SpaController::class, 'getOtherSpas']);
Route::get('/spas', [SpaController::class, 'index']);
Route::get('/spas/{id}', [SpaController::class, 'show']);

// Flutter booking endpoints
Route::post('/flutter/create-booking', [FlutterBookingController::class, 'createBooking']);
Route::get('/flutter/booking-status', [FlutterBookingController::class, 'checkBookingStatus']);

// ── Test endpoint ─────────────────────────────────────────────────────────────
Route::get('/test', fn() => response()->json(['status' => 'api works']));

Route::get('/image/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);

    if (!file_exists($fullPath)) {
        return response()->json(['error' => 'Image not found'], 404);
    }

    $file = file_get_contents($fullPath);
    $mimeType = mime_content_type($fullPath);

    return response($file, 200)
        ->header('Content-Type', $mimeType);
})->where('path', '.*');

// ── Protected endpoints (Authentication required) ─────────────────────────────
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);


    // Customer bookings (only customers can access)
    Route::middleware(['role:customer'])->group(function () {
        Route::get('/my-bookings', [BookingController::class, 'myBookings']);
        Route::post('/bookings',   [BookingController::class, 'store']);

        Route::get('/reschedule-requests/booking/{bookingId}', [RescheduleRequestController::class, 'show']);
        Route::post('/reschedule-requests', [RescheduleRequestController::class, 'store']);
    });

    // Therapist endpoints (only therapists can access)
    Route::middleware(['role:therapist'])->group(function () {
        Route::get('/assigned-bookings',  [BookingController::class, 'assignedBookings']);
        Route::get('/therapist/schedule', [BookingController::class, 'therapistSchedule']);
        Route::patch('/bookings/{id}/status', [BookingController::class, 'updateStatus']);
    });
});

Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
    Route::post('/ratings', [RatingController::class, 'store']);
    Route::get('/ratings/booking/{bookingId}', [RatingController::class, 'show']);
});
