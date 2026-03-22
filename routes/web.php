<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\RegisteredSpaController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CustomerAppointmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\HR\HRController;
use App\Http\Controllers\Insights\DecisionSupportController;
use App\Http\Controllers\Insights\ReportsController;
use App\Http\Controllers\InventoryImportExportController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\OnlineBookingCheckoutController;
use App\Http\Controllers\Owner\RolePermissionController as OwnerRolePermissionController;
use App\Http\Controllers\Owner\SpaProfileController;
use App\Http\Controllers\Owner\SubscriptionController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymongoWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceImportExportController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StaffAvailabilityController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TreatmentController;
use App\Http\Middleware\LandingPageRedirect;
use App\Mail\WelcomeMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SMTP Test Route (temporary)
|--------------------------------------------------------------------------
*/

Route::get('/send-mail', [MailController::class, 'sendWelcomeMail']);
Route::get('/test-mail', function () {
    $data = [
        'name'    => 'Test User',
        'message' => 'This is a test email from Mailtrap!'
    ];
    Mail::to('anyone@example.com')->send(new WelcomeMail($data));
    return 'Email sent! Check your Mailtrap inbox.';
});

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'force.password.change'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('role:owner|manager|therapist|receptionist')
        ->name('dashboard');
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
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    });

/*
|--------------------------------------------------------------------------
| Landing Page (public)
|--------------------------------------------------------------------------
*/
Route::get('/', [LandingController::class, 'index'])
    ->middleware(LandingPageRedirect::class)
    ->name('landing.page');

/*
/*
|--------------------------------------------------------------------------
| Landing Page Online Booking (public)
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
/*
|--------------------------------------------------------------------------
| Customer Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/my-appointments', [CustomerAppointmentController::class, 'appointments'])->name('customer.appointments');
    Route::get('/my-schedule', [CustomerAppointmentController::class, 'schedule'])->name('customer.schedule');
});

/*
|--------------------------------------------------------------------------
| Operations: Booking
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:create booking'])->group(function () {
    Route::get('/booking', [BookingController::class, 'create'])->name('booking');
    Route::post('/booking', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/booking/available-therapists', [BookingController::class, 'availableTherapists'])
        ->name('booking.available-therapists');
});

Route::post('/bookings/online', [BookingController::class, 'storeOnline'])
    ->middleware(['auth', 'role:customer'])
    ->name('bookings.online.store');

Route::middleware(['auth', 'permission:view appointments'])->group(function () {
    Route::get('/booking/history', [BookingController::class, 'history'])->name('bookings.history');
});

/*
|--------------------------------------------------------------------------
| Schedule
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:view schedule|manage schedule'])->group(function () {
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::get('/schedule/data', [ScheduleController::class, 'data'])->name('schedule.data');
});

/*
|--------------------------------------------------------------------------
| Staff Availability
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:view staff availability|manage staff availability'])->group(function () {
    Route::get('/staff-availability', [StaffAvailabilityController::class, 'index'])->name('staff.availability');
    Route::post('/staff-availability', [StaffAvailabilityController::class, 'store'])->name('staff.availability.store');
});

/*
|--------------------------------------------------------------------------
| Branch Routes
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
        Route::get('/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
        Route::put('/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Services / Treatments / Packages
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:view services|manage services'])->group(function () {
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
});

Route::middleware(['auth', 'permission:manage services'])->group(function () {
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
| API: Operating Hours
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:create booking|manage services'])->group(function () {
    Route::get('/api/operating-hours/{branch}/{day}', function ($branchId, $day) {
        $hours = \App\Models\OperatingHours::where('branch_id', $branchId)
            ->where('day_of_week', $day)
            ->first();

        if (!$hours) return response()->json(['is_closed' => true]);

        return response()->json([
            'is_closed'    => $hours->is_closed,
            'opening_time' => $hours->opening_time,
            'closing_time' => $hours->closing_time,
        ]);
    })->name('api.operating-hours');
});

/*
|--------------------------------------------------------------------------
| Staff
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:view staff|manage staff'])->group(function () {
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::get('/staff/{staff}', [StaffController::class, 'show'])->name('staff.show');
});

Route::middleware(['auth', 'permission:manage staff'])->group(function () {
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
});

/*
|--------------------------------------------------------------------------
| Inventory — ✅ FIXED: was role:manager, now permission-based
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('inventory')->name('inventory.')->group(function () {

    // View products — view inventory OR manage inventory
    Route::middleware('permission:view inventory|manage inventory')->group(function () {
        Route::get('/products', [\App\Http\Controllers\InventoryController::class, 'products'])
            ->name('products');
    });

    // View logs — view inventory logs OR manage inventory
    Route::middleware('permission:view inventory logs|manage inventory')->group(function () {
        Route::get('/logs', [\App\Http\Controllers\InventoryController::class, 'logs'])
            ->name('logs');
    });

    // Manage inventory — manage inventory only
    Route::middleware('permission:manage inventory')->group(function () {
        Route::post('/products', [\App\Http\Controllers\InventoryController::class, 'store'])
            ->name('products.store');
        Route::post('/products/{product}/deduct', [\App\Http\Controllers\InventoryController::class, 'deduct'])
            ->name('products.deduct');
        Route::put('/products/{product}', [\App\Http\Controllers\InventoryController::class, 'update'])
            ->name('products.update');
        Route::delete('/products/{product}', [\App\Http\Controllers\InventoryController::class, 'destroy'])
            ->name('products.destroy');

        Route::get('/products/export', [InventoryImportExportController::class, 'exportProducts'])
            ->name('products.export');
        Route::post('/products/import', [InventoryImportExportController::class, 'importProducts'])
            ->name('products.import');
    });
});

/*
|--------------------------------------------------------------------------
| Insights
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:view decision support'])->group(function () {
    Route::get('/decision-support', [DecisionSupportController::class, 'index'])
        ->name('decision-support.index');
});

Route::middleware(['auth', 'permission:view reports'])->group(function () {
    Route::get('/reports', [ReportsController::class, 'index'])
        ->name('reports.index');
});

/*
|--------------------------------------------------------------------------
| Appointments
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
});

/*
|--------------------------------------------------------------------------
| Administration (Admin only)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/registered-spas', [RegisteredSpaController::class, 'index'])->name('registered-spas.index');
    Route::get('/registered-spas/{spa}/edit', [RegisteredSpaController::class, 'edit'])->name('registered-spas.edit');
    Route::put('/registered-spas/{spa}', [RegisteredSpaController::class, 'update'])->name('registered-spas.update');
    Route::delete('/registered-spas/{spa}', [RegisteredSpaController::class, 'destroy'])->name('registered-spas.destroy');

    Route::middleware('permission:manage users')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::put('/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.updateRole');
    });

    Route::middleware('permission:manage roles')->group(function () {
        Route::get('/roles-permissions', [RolePermissionController::class, 'index'])->name('roles-permissions.index');
        Route::get('/roles-permissions/{role}/edit', [RolePermissionController::class, 'edit'])->name('roles-permissions.edit');
        Route::put('/roles-permissions/{role}', [RolePermissionController::class, 'update'])->name('roles-permissions.update');
    });

    Route::middleware('permission:manage settings')->group(function () {
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
        Route::get('/spa-profile', [SpaProfileController::class, 'edit'])
            ->name('spa-profile.edit');
        Route::patch('/spa-profile', [SpaProfileController::class, 'update'])
            ->name('spa-profile.update');
        Route::post('/spa-profile/documents', [SpaProfileController::class, 'uploadDocument'])
            ->name('spa-profile.documents.upload');
        Route::delete('/spa-profile/documents/{document}', [SpaProfileController::class, 'destroyDocument'])
            ->name('spa-profile.documents.destroy');

        // Roles & Permissions
        Route::get('/roles-permissions', [OwnerRolePermissionController::class, 'index'])
            ->name('roles-permissions.index');
        Route::get('/roles-permissions/{role}/edit', [OwnerRolePermissionController::class, 'edit'])
            ->name('roles-permissions.edit');
        Route::put('/roles-permissions/{role}', [OwnerRolePermissionController::class, 'update'])
            ->name('roles-permissions.update');

        // Subscription Management
        Route::get('/subscription', [SubscriptionController::class, 'index'])
            ->name('subscription.index');
        Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])
            ->name('subscription.checkout');
        Route::get('/subscription/success', [SubscriptionController::class, 'success'])
            ->name('subscription.success');
        Route::get('/subscription/cancel', [SubscriptionController::class, 'cancel'])
            ->name('subscription.cancel');
        Route::post('/subscription/cancel-subscription', [SubscriptionController::class, 'cancelSubscription'])
            ->name('subscription.cancel-subscription');
    });

// =====================================================
// HR Routes
// =====================================================
Route::middleware(['auth', 'role:hr|owner'])
    ->prefix('hr')
    ->name('hr.')
    ->group(function () {

        Route::get('/dashboard', [HRController::class, 'dashboard'])->name('dashboard');

        // Hiring
        Route::middleware('permission:view hiring|manage hiring')->group(function () {
            Route::get('/hiring', [HRController::class, 'hiring'])->name('hiring');
        });
        Route::middleware('permission:manage hiring')->group(function () {
            Route::post('/hiring', [HRController::class, 'hiringStore'])->name('hiring.store');
            Route::put('/hiring/{posting}', [HRController::class, 'hiringUpdate'])->name('hiring.update');
            Route::delete('/hiring/{posting}', [HRController::class, 'hiringDestroy'])->name('hiring.destroy');
        });

        // Applications
        Route::middleware('permission:view applications|manage applications')->group(function () {
            Route::get('/applications', [HRController::class, 'applications'])->name('applications');
        });
        Route::middleware('permission:manage applications')->group(function () {
            Route::post('/applications', [HRController::class, 'applicationsStore'])->name('applications.store');
            Route::post('/applications/{applicant}/schedule-interview', [HRController::class, 'applicationsScheduleInterview'])->name('applications.schedule-interview');
        });

        // Interviews
        Route::middleware('permission:view interviews|manage interviews')->group(function () {
            Route::get('/interviews', [HRController::class, 'interviews'])->name('interviews');
        });
        Route::middleware('permission:manage interviews')->group(function () {
            Route::post('/interviews/{interview}/approve', [HRController::class, 'interviewApprove'])->name('interviews.approve');
            Route::post('/interviews/{interview}/reject', [HRController::class, 'interviewReject'])->name('interviews.reject');
            Route::post('/interviews/{interview}/create-staff', [HRController::class, 'createStaffFromInterview'])->name('interviews.create-staff');
        });

        // Attendance
        Route::middleware('permission:view attendance|manage attendance')->group(function () {
            Route::get('/attendance', [HRController::class, 'attendance'])->name('attendance');
        });
        Route::middleware('permission:manage attendance')->group(function () {
            Route::post('/attendance', [HRController::class, 'attendanceStore'])->name('attendance.store');
        });

        // Payroll
        Route::middleware('permission:view payroll|manage payroll')->group(function () {
            Route::get('/payroll', [HRController::class, 'payroll'])->name('payroll');
        });
        Route::middleware('permission:manage payroll')->group(function () {
            Route::post('/payroll/generate', [HRController::class, 'payrollGenerate'])->name('payroll.generate');
            Route::post('/payroll/{payroll}/finalize', [HRController::class, 'payrollFinalize'])->name('payroll.finalize');
        });
    });

// =====================================================
// Finance Routes
// =====================================================
Route::middleware(['auth', 'role:finance|owner'])
    ->prefix('finance')
    ->name('finance.')
    ->group(function () {
        Route::get('/dashboard', [FinanceController::class, 'dashboard'])->name('dashboard');

        Route::middleware('permission:view revenue|manage revenue')
            ->get('/revenue', [FinanceController::class, 'revenue'])->name('revenue');

        Route::middleware('permission:view billing|manage billing')
            ->get('/billing', fn() => view('finance.billing'))->name('billing');

        Route::middleware('permission:view finance inventory|manage finance inventory')
            ->get('/inventory', fn() => view('finance.inventory'))->name('inventory');
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
