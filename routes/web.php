<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\RegisteredSpaController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Api\FlutterBookingController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CustomerAppointmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\PayrollController;
use App\Http\Controllers\Finance\RevenueController;
use App\Http\Controllers\HR\ApplicationController;
use App\Http\Controllers\HR\AttendanceController;
use App\Http\Controllers\HR\BranchDeploymentController;
use App\Http\Controllers\HR\HiringController;
use App\Http\Controllers\HR\InterviewController;
use App\Http\Controllers\Insights\DecisionSupportController;
use App\Http\Controllers\Insights\ReportsController;
use App\Http\Controllers\InventoryImportExportController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\OnlineBookingCheckoutController;
use App\Http\Controllers\Owner\RolePermissionController as OwnerRolePermissionController;
use App\Http\Controllers\Owner\SpaProfileController;
use App\Http\Controllers\Owner\SubscriptionController;
use App\Http\Controllers\Owner\WorkforceFinanceSuiteController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymongoWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RescheduleRequestController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceImportExportController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TreatmentController;
use App\Http\Middleware\LandingPageRedirect;
use App\Http\Controllers\TherapistPerformanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SMTP Test Route (temporary)
|--------------------------------------------------------------------------
*/

// Route::get('/send-mail', [MailController::class, 'sendWelcomeMail']);
// Route::get('/test-mail', function () {
//     $data = [
//         'name'    => 'Test User',
//         'message' => 'This is a test email from Mailtrap!',
//     ];

//     Mail::to('anyone@example.com')->send(new WelcomeMail($data));

//     return 'Email sent! Check your Mailtrap inbox.';
// });



// web.php - PUT THESE FIRST before anything else

Route::options('/{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, X-Token-Auth, Authorization, Origin, Accept')
        ->header('Access-Control-Allow-Credentials', 'true')
        ->header('Access-Control-Max-Age', '86400');
})->where('any', '.*');

Route::get('/storage/branch_profiles/{filename}', function ($filename) {
    $fullPath = storage_path('app/public/branch_profiles/' . $filename);
    if (!file_exists($fullPath)) {
        // Try without branch_profiles folder
        $fullPath = storage_path('app/public/' . $filename);
        if (!file_exists($fullPath)) {
            abort(404);
        }
    }

    $file = file_get_contents($fullPath);
    $mimeType = mime_content_type($fullPath);

    return response($file, 200)
        ->header('Content-Type', $mimeType)
        ->header('Content-Length', filesize($fullPath))
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization')
        ->header('Cache-Control', 'public, max-age=3600');
})->where('filename', '.*')->withoutMiddleware(['auth', 'auth:sanctum']);

// Also add a generic storage route for other files
Route::get('/storage/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);
    if (!file_exists($fullPath)) abort(404);

    $file = file_get_contents($fullPath);
    $mimeType = mime_content_type($fullPath);

    return response($file, 200)
        ->header('Content-Type', $mimeType)
        ->header('Content-Length', filesize($fullPath))
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept')
        ->header('Cache-Control', 'public, max-age=3600');
})->where('path', '.*')->withoutMiddleware(['auth', 'auth:sanctum']);

// Add these for the redirect callbacks
Route::get('/flutter/payment/success', [FlutterBookingController::class, 'paymentSuccess'])
    ->name('flutter.payment.success');

Route::get('/flutter/payment/cancel', [FlutterBookingController::class, 'paymentCancel'])
    ->name('flutter.payment.cancel');

/*
|--------------------------------------------------------------------------
| Landing Page (public)
|--------------------------------------------------------------------------
*/

Route::get('/', [LandingController::class, 'index'])
    ->middleware(LandingPageRedirect::class)
    ->name('landing.page');

/*
|--------------------------------------------------------------------------
| Landing Page Online Booking / PayMongo
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    Route::post('/bookings/online/checkout', [OnlineBookingCheckoutController::class, 'store'])
        ->name('bookings.online.checkout');

    Route::get('/bookings/online/payment/success', [OnlineBookingCheckoutController::class, 'success'])
        ->name('bookings.online.payment.success');

    Route::get('/bookings/online/payment/cancel', [OnlineBookingCheckoutController::class, 'cancel'])
        ->name('bookings.online.payment.cancel');
});

Route::post('/webhooks/paymongo', [PaymongoWebhookController::class, 'handle'])
    ->name('webhooks.paymongo');

/*
|--------------------------------------------------------------------------
| Customer Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    Route::get('/my-appointments', [CustomerAppointmentController::class, 'appointments'])
        ->name('customer.appointments');

    Route::get('/my-schedule', [CustomerAppointmentController::class, 'schedule'])
        ->name('customer.schedule');

    Route::post('/bookings/online', [BookingController::class, 'storeOnline'])
        ->middleware('role:customer')
        ->name('bookings.online.store');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/reschedule-requests', [RescheduleRequestController::class, 'store'])
        ->name('reschedule.store');

    Route::get('/reschedule-requests/{booking}/status', [RescheduleRequestController::class, 'status'])
        ->name('reschedule.status');
    Route::post('/ratings', [App\Http\Controllers\RatingController::class, 'store'])->name('ratings.store');
});

Route::middleware(['auth'])->group(function () {
    Route::patch('/customer/profile', [ProfileController::class, 'updateCustomer'])
        ->name('customer.profile.update');
});

/*
|--------------------------------------------------------------------------
| Staff Dashboard
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'force.password.change'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('role:owner|manager|therapist|receptionist')
        ->name('dashboard');

     Route::post('/appointments/{booking}/fix-duration', [BookingController::class, 'fixDuration'])
        ->middleware('branch.permission:edit appointments')
        ->name('appointments.fix-duration');

    Route::post('/appointments/fix-all-durations', [BookingController::class, 'fixAllDurations'])
        ->middleware('branch.permission:edit appointments')
        ->name('appointments.fix-all-durations');
});

/*
|--------------------------------------------------------------------------
| Admin Dashboard
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');
    });

/*
|--------------------------------------------------------------------------
| Business-side Routes (branch-aware permissions)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'force.password.change'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Operations: Book Appointment
    |--------------------------------------------------------------------------
    */
    Route::middleware('branch.permission:book appointments')->group(function () {
        Route::get('/booking', [BookingController::class, 'create'])->name('booking');
        Route::post('/booking', [BookingController::class, 'store'])->name('bookings.store');
        Route::get('/booking/available-therapists', [BookingController::class, 'availableTherapists'])
            ->name('booking.available-therapists');
    });

    /*
    |--------------------------------------------------------------------------
    | Appointments
    |--------------------------------------------------------------------------
    */
    Route::middleware('branch.permission:view appointments')->group(function () {
        Route::get('/appointments', [BookingController::class, 'adminIndex'])->name('appointments.index');
        Route::get('/booking/history', [BookingController::class, 'history'])->name('bookings.history');
    });

    Route::middleware('branch.permission:edit appointments')->group(function () {
        Route::post('/appointments/{booking}/reserve', [BookingController::class, 'reserve'])->name('appointments.reserve');
        Route::put('/appointments/{booking}/status', [BookingController::class, 'updateStatus'])->name('appointments.updateStatus');
        Route::put('/appointments/{booking}', [BookingController::class, 'update'])->name('appointments.update');
    });

    Route::middleware('branch.permission:delete appointments')->group(function () {
        Route::delete('/appointments/{id}', [BookingController::class, 'destroy'])->name('appointments.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    */
    Route::middleware('branch.permission:view schedule')->group(function () {
        Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
        Route::get('/schedule/data', [ScheduleController::class, 'data'])->name('schedule.data');
    });

    /*
    |--------------------------------------------------------------------------
    | Branch Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('branch.permission:view branches')->group(function () {
        Route::post('/branch/switch', [BranchController::class, 'switch'])->name('branch.switch');
        Route::get('/branch/current', [BranchController::class, 'getCurrentBranch'])->name('branch.current');

        Route::prefix('branches')->group(function () {
            Route::get('/', [BranchController::class, 'index'])->name('branches.index');
            Route::get('/{branch}', [BranchController::class, 'show'])->name('branches.show');
        });
    });

    Route::middleware('branch.permission:create branches')->group(function () {
        Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
    });

    Route::middleware('branch.permission:edit branches')->group(function () {
        Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
        Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    });

    Route::middleware('branch.permission:delete branches')->group(function () {
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Services / Treatments / Packages
    |--------------------------------------------------------------------------
    */
    Route::middleware('branch.permission:view services')->group(function () {
        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    });

    Route::middleware('branch.permission:create treatments,edit treatments,delete treatments,create packages,edit packages,delete packages')->group(function () {
        Route::resource('treatments', TreatmentController::class)->except(['index', 'create', 'edit']);
        Route::resource('packages', PackageController::class)->except(['index', 'create', 'edit']);

        Route::get('/services/treatments/export', [ServiceImportExportController::class, 'exportTreatments'])
            ->name('treatments.export');

        Route::post('/services/treatments/import', [ServiceImportExportController::class, 'importTreatments'])
            ->name('treatments.import');

        Route::get('/services/packages/export', [ServiceImportExportController::class, 'exportPackages'])
            ->name('packages.export');

        Route::post('/services/packages/import', [ServiceImportExportController::class, 'importPackages'])
            ->name('packages.import');
    });

    /*
    |--------------------------------------------------------------------------
    | Staff
    |--------------------------------------------------------------------------
    */
    Route::middleware('branch.permission:view staff')->group(function () {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/{staff}', [StaffController::class, 'show'])->name('staff.show');
    });

    Route::middleware('branch.permission:create staff')->group(function () {
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    });

    Route::middleware('branch.permission:edit staff')->group(function () {
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    });

    Route::middleware('branch.permission:delete staff')->group(function () {
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
    });

    // Therapist Performance
    Route::middleware(['auth', 'verified', 'role:therapist'])->group(function () {
        Route::get('/therapist/performance', [App\Http\Controllers\TherapistPerformanceController::class, 'index'])
            ->name('therapist.performance');
    });

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */
    Route::prefix('inventory')->name('inventory.')->group(function () {

        Route::middleware('branch.permission:view inventory')->group(function () {
            Route::get('/products', [\App\Http\Controllers\InventoryController::class, 'products'])
                ->name('products');
        });

        Route::middleware('branch.permission:view inventory logs')->group(function () {
            Route::get('/logs', [\App\Http\Controllers\InventoryController::class, 'logs'])
                ->name('logs');
        });

        Route::middleware('branch.permission:create inventory items')->group(function () {
            Route::post('/products', [\App\Http\Controllers\InventoryController::class, 'store'])
                ->name('products.store');

            Route::post('/products/import', [InventoryImportExportController::class, 'importProducts'])
                ->name('products.import');
        });

        Route::middleware('branch.permission:edit inventory items')->group(function () {
            Route::post('/products/{product}/deduct', [\App\Http\Controllers\InventoryController::class, 'deduct'])
                ->name('products.deduct');

            Route::put('/products/{product}', [\App\Http\Controllers\InventoryController::class, 'update'])
                ->name('products.update');

            Route::get('/products/export', [InventoryImportExportController::class, 'exportProducts'])
                ->name('products.export');
        });

        Route::middleware('branch.permission:delete inventory items')->group(function () {
            Route::delete('/products/{product}', [\App\Http\Controllers\InventoryController::class, 'destroy'])
                ->name('products.destroy');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Insights
    |--------------------------------------------------------------------------
    */
    Route::middleware('branch.permission:view decision support')->group(function () {
        Route::get('/decision-support', [DecisionSupportController::class, 'index'])
            ->name('decision-support.index');
    });

    Route::middleware('branch.permission:view reports')->group(function () {
        Route::get('/reports', [ReportsController::class, 'index'])
            ->name('reports.index');
    });

    /*
    |--------------------------------------------------------------------------
    | HR Modules
    |--------------------------------------------------------------------------
    */
    Route::middleware('branch.permission:view hr dashboard')->group(function () {
        // Rename to hrOverview in controller if your current method name still has a dash.
        Route::get('/hr-overview', [HiringController::class, 'hrOverview'])->name('hr-overview');
    });

    Route::get('/hr-dashboard', [HiringController::class, 'hrOverview'])->name('hr.dashboard');

    // Hiring
    Route::middleware('branch.permission:view hiring')->group(function () {
        Route::get('/hiring', [HiringController::class, 'index'])->name('hiring.index');
    });

    Route::middleware('branch.permission:create hiring')->group(function () {
        Route::post('/hiring', [HiringController::class, 'store'])->name('hiring.store');
    });

    Route::middleware('branch.permission:edit hiring')->group(function () {
        Route::put('/hiring/{posting}', [HiringController::class, 'update'])->name('hiring.update');
    });

    Route::middleware('branch.permission:delete hiring')->group(function () {
        Route::delete('/hiring/{posting}', [HiringController::class, 'destroy'])->name('hiring.destroy');
    });

    // Applications
    Route::middleware('branch.permission:view applications')->group(function () {
        Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
    });

    Route::middleware('branch.permission:edit applications')->group(function () {
        Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');
        Route::post('/applications/{applicant}/schedule-interview', [ApplicationController::class, 'scheduleInterview'])
            ->name('applications.schedule-interview');
    });

    Route::middleware('branch.permission:delete applications')->group(function () {
        Route::delete('/applications/{applicant}', [ApplicationController::class, 'destroy'])->name('applications.destroy');
    });

    // Interviews
    Route::middleware('branch.permission:view interviews')->group(function () {
        Route::get('/interviews', [InterviewController::class, 'index'])->name('interviews.index');
    });

    Route::middleware('branch.permission:create interviews,edit interviews')->group(function () {
        Route::post('/interviews/{interview}/approve', [InterviewController::class, 'approve'])->name('interviews.approve');
        Route::post('/interviews/{interview}/reject', [InterviewController::class, 'reject'])->name('interviews.reject');
        Route::post('/interviews/{interview}/create-staff', [InterviewController::class, 'createStaff'])
            ->name('interviews.create-staff');
    });

    // Deployment
    Route::middleware('branch.permission:view deployments')->group(function () {
        Route::get('/deployment', [BranchDeploymentController::class, 'index'])
            ->name('deployment.index');
    });

    Route::middleware('branch.permission:create deployments')->group(function () {
        Route::post('/branch-deployments', [BranchDeploymentController::class, 'store'])
            ->name('branch-deployments.store');
    });

    Route::middleware('branch.permission:approve deployments')->group(function () {
        Route::post('/branch-deployments/{deployment}/approve', [BranchDeploymentController::class, 'approve'])
            ->name('branch-deployments.approve');

        Route::post('/branch-deployments/{deployment}/reject', [BranchDeploymentController::class, 'reject'])
            ->name('branch-deployments.reject');
    });

    Route::middleware('branch.permission:delete deployments')->group(function () {
        Route::post('/branch-deployments/{deployment}/cancel', [BranchDeploymentController::class, 'cancel'])
            ->name('branch-deployments.cancel');
    });

    // Attendance & Leave
    Route::middleware('branch.permission:view attendance,view leave requests,create leave requests,edit leave requests,delete leave requests')->group(function () {
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    });

    Route::middleware('branch.permission:edit attendance')->group(function () {
        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    });

    /*
    |--------------------------------------------------------------------------
    | Finance Modules
    |--------------------------------------------------------------------------
    */
    Route::middleware('branch.permission:view payroll')->group(function () {
        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    });

    Route::middleware('branch.permission:edit payroll')->group(function () {
        Route::post('/payroll/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
        Route::post('/payroll/{payroll}/finalize', [PayrollController::class, 'finalize'])->name('payroll.finalize');
    });

    Route::middleware('branch.permission:view revenue')->group(function () {
        Route::get('/revenue', [RevenueController::class, 'index'])->name('revenue.index');
    });

    Route::middleware('branch.permission:view billing')->group(function () {
        Route::get('/billing', fn() => view('finance.billing'))->name('billing.index');
    });

    Route::middleware('branch.permission:create billing')->group(function () {
        // Wala pang billing create routes here.
    });

    Route::middleware('branch.permission:edit billing')->group(function () {
        // Wala pang billing edit routes here.
    });

    Route::middleware('branch.permission:delete billing')->group(function () {
        // Wala pang billing delete routes here.
    });

    Route::middleware('branch.permission:view finance inventory')->group(function () {
        Route::get('/finance-inventory', fn() => view('finance.inventory'))->name('finance-inventory.index');
    });
});

/*
|--------------------------------------------------------------------------
| Administration (Admin only, global permissions)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::middleware('permission:view registered spas')->group(function () {
            Route::get('/registered-spas', [RegisteredSpaController::class, 'index'])->name('registered-spas.index');
        });

        // No separate "delete registered spas" permission exists in your current seeder,
        // so edit permission is currently the closest match for edit/update/destroy.
        Route::middleware('permission:edit registered spas')->group(function () {
            Route::get('/registered-spas/{spa}/edit', [RegisteredSpaController::class, 'edit'])->name('registered-spas.edit');
            Route::put('/registered-spas/{spa}', [RegisteredSpaController::class, 'update'])->name('registered-spas.update');
            Route::delete('/registered-spas/{spa}', [RegisteredSpaController::class, 'destroy'])->name('registered-spas.destroy');
        });

        Route::middleware('permission:view registered users')->group(function () {
            Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        });

        Route::middleware('permission:edit registered users')->group(function () {
            Route::put('/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.updateRole');
        });

        Route::middleware('permission:delete registered users')->group(function () {
            Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
        });

        Route::middleware('permission:view system roles')->group(function () {
            Route::get('/roles-permissions', [RolePermissionController::class, 'index'])->name('roles-permissions.index');
        });

        Route::middleware('permission:edit system roles')->group(function () {
            Route::get('/roles-permissions/{role}/edit', [RolePermissionController::class, 'edit'])->name('roles-permissions.edit');
            Route::put('/roles-permissions/{role}', [RolePermissionController::class, 'update'])->name('roles-permissions.update');
        });

        Route::middleware('permission:manage system settings')->group(function () {
            Route::get('/settings', fn() => view('settings'))->name('settings.index');
        });
    });

/*
|--------------------------------------------------------------------------
| Setup Wizard (Owner Only)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'owner-only'])->group(function () {
    Route::get('/setup/index', [SetupController::class, 'index'])->name('setup.index');
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
| Owner Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        // Spa Profile
        Route::get('/spa-profile', [SpaProfileController::class, 'edit'])->name('spa-profile.edit');
        Route::patch('/spa-profile', [SpaProfileController::class, 'update'])->name('spa-profile.update');
        Route::post('/spa-profile/documents', [SpaProfileController::class, 'uploadDocument'])->name('spa-profile.documents.upload');
        Route::delete('/spa-profile/documents/{document}', [SpaProfileController::class, 'destroyDocument'])->name('spa-profile.documents.destroy');

        // Roles & Permissions
        Route::get('/roles-permissions', [OwnerRolePermissionController::class, 'index'])->name('roles-permissions.index');
        Route::get('/roles-permissions/{role}/edit', [OwnerRolePermissionController::class, 'edit'])->name('roles-permissions.edit');
        Route::put('/roles-permissions/{role}', [OwnerRolePermissionController::class, 'update'])->name('roles-permissions.update');

        // Subscription Management
        Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
        Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
        Route::get('/subscription/success', [SubscriptionController::class, 'success'])->name('subscription.success');
        Route::get('/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
        Route::post('/subscription/cancel-subscription', [SubscriptionController::class, 'cancelSubscription'])->name('subscription.cancel-subscription');
    });

Route::middleware(['auth', 'verified', 'role:owner'])->group(function () {
    Route::get('/owner/workforce-finance-suite', [WorkforceFinanceSuiteController::class, 'index'])
        ->name('owner.workforce-finance-suite.index');

    Route::put('/owner/workforce-finance-suite', [WorkforceFinanceSuiteController::class, 'update'])
        ->name('owner.workforce-finance-suite.update');
});

// =====================================================
// Owner/Manager: Reschedule Approvals
// =====================================================
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/reschedule-requests', [RescheduleRequestController::class, 'index'])
        ->middleware('branch.permission:edit appointments')
        ->name('reschedule.index');

    Route::post('/reschedule-requests/{rescheduleRequest}/approve', [RescheduleRequestController::class, 'approve'])
        ->middleware('branch.permission:edit appointments')
        ->name('reschedule.approve');

    Route::post('/reschedule-requests/{rescheduleRequest}/reject', [RescheduleRequestController::class, 'reject'])
        ->middleware('branch.permission:edit appointments')
        ->name('reschedule.reject');
});

/*
|--------------------------------------------------------------------------
| User Profile
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
});

require __DIR__ . '/auth.php';
Route::get('/api/spas/nearby', [LandingController::class, 'nearbySpasList'])->middleware('auth');

require __DIR__.'/auth.php';
