<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\SpaController;
use App\Http\Middleware\EnsureMobileAccess;
use App\Http\Controllers\OnlineBookingCheckoutController;
use App\Http\Controllers\CustomerAppointmentController;
use App\Http\Controllers\Api\FlutterBookingController;
use App\Http\Controllers\Api\TherapistPerformanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\AttendanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// CORS preflight - UPDATE THIS TO INCLUDE PATCH
Route::options('/{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, X-Token-Auth, Authorization, Origin, Accept')
        ->header('Access-Control-Allow-Credentials', 'true')
        ->header('Access-Control-Max-Age', '86400');
})->where('any', '.*');

// Auth endpoints are rate-limited per IP. Each prefix keeps its own counter.
Route::post('/register',        [AuthController::class, 'register'])->middleware('throttle:5,1,api-register');
Route::post('/login',           [AuthController::class, 'login'])->middleware('throttle:10,1,api-login');
Route::post('/verify-email',    [AuthController::class, 'verifyEmail'])->middleware('throttle:10,1,api-verify');
Route::post('/resend-otp',      [AuthController::class, 'resendOtp'])->middleware('throttle:3,1,api-resend');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1,api-forgot');
Route::post('/reset-password',  [AuthController::class, 'resetPassword'])->middleware('throttle:5,1,api-reset');

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

// ── Public Spa endpoints ──
Route::get('/spas/cavite',  [SpaController::class, 'cavite']);
Route::get('/spas/nearby',  [SpaController::class, 'nearby']);
Route::get('/spas/other',   [SpaController::class, 'getOtherSpas']);
Route::get('/featured-spas', [SpaController::class, 'featured']);
Route::get('/spas',         [SpaController::class, 'index']);
Route::get('/spas/{id}',    [SpaController::class, 'show'])->whereNumber('id');
Route::get('/spas/search',             [SpaController::class, 'search']);
Route::get('/spas/search-suggestions', [SpaController::class, 'searchSuggestions']);
// Ratings & Reviews through the web's own controller.
Route::get('/spas/{spaId}/{branchId}/reviews', [\App\Http\Controllers\LandingController::class, 'spaReviews'])
    ->whereNumber(['spaId', 'branchId']);

// ── Test endpoint ─────────────────────────────────────────────────────────────
Route::get('/test', fn() => response()->json(['status' => 'api works']));

// Public images only (allowlist in PublicFileController).
Route::get('/image/{path}', [\App\Http\Controllers\PublicFileController::class, 'image'])->where('path', '.*');

// Protected endpoints (Authentication required)
Route::middleware(['auth:sanctum', EnsureMobileAccess::class])->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
    Route::put('/me',             [AuthController::class, 'updateProfile']);
    Route::put('/me/password',    [AuthController::class, 'updatePassword']);
    Route::patch('/profile', [ProfileController::class, 'updateCustomerApi'])->middleware('role:customer');

    // Profile photo and staff identity verification: same rules as the web
    // profile page (Api\ProfileIdentityController, staff verification Phase 6).
    Route::get('/me/identity',                  [\App\Http\Controllers\Api\ProfileIdentityController::class, 'show']);
    Route::post('/me/photo',                    [\App\Http\Controllers\Api\ProfileIdentityController::class, 'updatePhoto'])->middleware('throttle:10,1,api-photo');
    Route::delete('/me/photo',                  [\App\Http\Controllers\Api\ProfileIdentityController::class, 'destroyPhoto']);
    Route::post('/me/identity-verification',    [\App\Http\Controllers\Api\ProfileIdentityController::class, 'submit'])->middleware('throttle:6,1,api-identity');
    Route::delete('/me/identity-verification',  [\App\Http\Controllers\Api\ProfileIdentityController::class, 'withdraw']);
    // Profile photos are private; who may see one: IdentityPolicy::viewPhoto.
    Route::get('/profile-photos/{user}', [\App\Http\Controllers\ProfilePhotoController::class, 'show'])
        ->whereNumber('user')
        ->name('api.profile-photos.show');
    Route::get('/therapist/attendance', [AttendanceController::class, 'myAttendance']);
    Route::post('/therapist/attendance/clock-in',  [AttendanceController::class, 'clockIn']);
    Route::post('/therapist/attendance/clock-out', [AttendanceController::class, 'clockOut']);
    // Attendance-aware booking Phase 2 (additive): what clocking in / out now would record.
    Route::get('/therapist/attendance/clock-preview', [AttendanceController::class, 'clockPreview']);

    // Leave requests and "can't make it" through the web's own controllers,
    // behind the same route checks as the web (Phase 6.1): filing leave needs
    // `create leave requests` (customers never have it), and "can't make it"
    // needs the reassignment permission or "Does treatments".
    Route::post('/leave-requests',     [\App\Http\Controllers\LeaveRequestController::class, 'store'])
        ->middleware('branch.permission:create leave requests');
    Route::get('/leave-requests/mine', [\App\Http\Controllers\LeaveRequestController::class, 'mine']);
    Route::get('/leave-requests/{leaveRequest}/affected-bookings', [\App\Http\Controllers\LeaveRequestController::class, 'affectedBookings']);
    Route::post('/appointments/{booking}/reassignment-requests', [\App\Http\Controllers\ReassignmentRequestController::class, 'store'])
        ->middleware('can:request-appointment-reassignment');
            // My Payslips: the web's HR\MyPayslipController logic, as JSON.
    Route::get('/my-payslips', [\App\Http\Controllers\Api\MyPayslipController::class, 'index']);
    Route::get('/my-payslips/{payslip}', [\App\Http\Controllers\Api\MyPayslipController::class, 'show'])
            ->whereNumber('payslip');

    // Customer bookings (only customers can access)
    Route::middleware(['role:customer'])->group(function () {
                // My Appointments and My Schedule through the web's own controller.
        Route::get('/my-appointments', [CustomerAppointmentController::class, 'appointments']);
        Route::get('/my-schedule',     [CustomerAppointmentController::class, 'schedule']);
                // Reschedule through the web's own controller (same rules as the web).
        Route::post('/reschedule-requests', [\App\Http\Controllers\RescheduleRequestController::class, 'store']);
        // Cancel through the web's own controller and rule (customer cancellation, Nov 2026).
        Route::post('/appointments/{booking}/cancel', [\App\Http\Controllers\CustomerCancellationController::class, 'store'])
            ->whereNumber('booking')
            ->middleware('throttle:10,1,api-cancel');

        // Online booking through the web's own controller, so web and mobile
        // share every rule (price, promo, 30 days, slots, therapists, leave).
        Route::get('/bookings/online/available-days',  [OnlineBookingCheckoutController::class, 'availableDays']);
        Route::get('/bookings/online/available-slots', [OnlineBookingCheckoutController::class, 'availableSlots']);
        Route::post('/bookings/online/checkout',       [OnlineBookingCheckoutController::class, 'store'])->middleware('throttle:10,1,api-checkout');
        Route::get('/bookings/online/status',          [FlutterBookingController::class, 'reservationStatus']);
        Route::post('/bookings/online/cancel',         [FlutterBookingController::class, 'cancelReservation']);
        Route::get('/bookings/online/my-dates',        [FlutterBookingController::class, 'myBookedDates']);

    });

    // Therapist endpoints: therapist role or "Performs treatments" (Q1b/Q48),
    // same gate as the web's My Performance.
    Route::middleware(['can:act-as-treatment-provider'])->group(function () {
        Route::get('/assigned-bookings',  [BookingController::class, 'assignedBookings']);
        Route::get('/therapist/schedule', [BookingController::class, 'therapistSchedule']);
        // Arrival code for online home service (same rules as the web).
        Route::post('/appointments/{booking}/arrival', [\App\Http\Controllers\Api\ArrivalController::class, 'store'])
            ->whereNumber('booking')
            ->middleware('throttle:10,1,api-arrival');
    });
});

Route::middleware(['auth:sanctum', EnsureMobileAccess::class, 'can:act-as-treatment-provider'])->group(function () {
    Route::get('/therapist/performance', [TherapistPerformanceController::class, 'index']);
});

Route::middleware(['auth:sanctum', EnsureMobileAccess::class, 'role:customer'])->group(function () {
    // Rating through the web's own controller: spa + therapist + photos.
    Route::post('/ratings', [\App\Http\Controllers\RatingController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Owner side of the app (owner mobile, Nov 2026) — view only
|--------------------------------------------------------------------------
| X-Branch-Id picks the branch (OwnerMobileBranch); then the same permission
| and plan checks as the web pages. Dashboard, today/upcoming appointments
| and the schedule are the web's own JSON endpoints.
*/
Route::middleware(['auth:sanctum', EnsureMobileAccess::class, 'role:owner', \App\Http\Middleware\OwnerMobileBranch::class])
    ->prefix('owner')
    ->group(function () {
        $owner = \App\Http\Controllers\Api\Owner\OwnerAppController::class;

        Route::get('/branches', [$owner, 'branches']);
        Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'liveData']);

        Route::middleware('branch.permission:view appointments')->group(function () use ($owner) {
            Route::get('/appointments', [\App\Http\Controllers\BookingController::class, 'liveData']);
            Route::get('/appointments/history', [$owner, 'history']);
        });

        Route::get('/schedule', [$owner, 'schedule'])
            ->middleware('branch.permission:view schedule');

        Route::get('/services', [$owner, 'services'])
            ->middleware('branch.permission:view services');

        Route::get('/inventory/products', [$owner, 'products'])
            ->middleware(['branch.permission:view inventory', 'plan:inventory_basic']);
        Route::get('/inventory/logs', [$owner, 'logs'])
            ->middleware(['branch.permission:view inventory logs', 'plan:inventory_basic']);

        Route::get('/reports', [$owner, 'reports'])
            ->middleware(['branch.permission:view reports', 'plan:insights']);
        Route::get('/decision-support', [$owner, 'decisionSupport'])
            ->middleware(['branch.permission:view decision support', 'plan:insights']);

        // Customer chat: the web's shared spa inbox (SpaChatController), which
        // checks "reply to customer messages" itself. Owners may reply from the app.
        Route::prefix('chat')->group(function () {
            $chat = \App\Http\Controllers\Chat\SpaChatController::class;

            Route::get('/unread', [$chat, 'unread']);
            Route::get('/conversations', [$chat, 'conversations']);
            Route::get('/conversations/{conversation}', [$chat, 'thread']);
            Route::post('/conversations/{conversation}', [$chat, 'send'])->middleware('throttle:30,1');
        });
    });
