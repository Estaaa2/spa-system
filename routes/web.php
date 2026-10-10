<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\BranchVerificationController;
use App\Http\Controllers\Admin\DocumentReviewController;
use App\Http\Controllers\Admin\RegisteredSpaController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Api\FlutterBookingController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CustomerAppointmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\BillingController;
use App\Http\Controllers\Finance\RevenueController;
use App\Http\Controllers\Finance\VendorBillController;
use App\Http\Controllers\HR\ApplicationController;
use App\Http\Controllers\HR\AttendanceController;
use App\Http\Controllers\HR\BranchDeploymentController;
use App\Http\Controllers\HR\HiringController;
use App\Http\Controllers\HR\InterviewController;
use App\Http\Controllers\HR\MyPayslipController;
use App\Http\Controllers\HR\PayrollController;
use App\Http\Controllers\HR\PayrollSetupController;
use App\Http\Controllers\HR\PayslipController;
use App\Http\Controllers\HR\PublicApplicationController;
use App\Http\Controllers\HR\StaffPayProfileController;
use App\Http\Controllers\Insights\DecisionSupportController;
use App\Http\Controllers\Insights\ReportsController;
use App\Http\Controllers\InventoryImportExportController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\OnlineBookingCheckoutController;
use App\Http\Controllers\Owner\RolePermissionController as OwnerRolePermissionController;
use App\Http\Controllers\Owner\SpaProfileController;
use App\Http\Controllers\Owner\SubscriptionController;
use App\Http\Controllers\Owner\WorkforceFinanceSuiteController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymongoWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReassignmentRequestController;
use App\Http\Controllers\RescheduleRequestController;
use App\Http\Controllers\ReplenishmentController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceImportExportController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\VerificationDocumentController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\LockedSpaController;
use App\Http\Controllers\Owner\TrialController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TherapistPerformanceController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\TreatmentRecipeController;
use App\Http\Middleware\LandingPageRedirect;
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

// CORS Middleware for all routes
Route::options('/{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, X-Token-Auth, Authorization, Origin, Accept')
        ->header('Access-Control-Allow-Credentials', 'true')
        ->header('Access-Control-Max-Age', '86400');
})->where('any', '.*');

if (! function_exists('corsResponse')) {
    function corsResponse($response)
    {
        return $response->header('Access-Control-Allow-Origin', '*')
                       ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                       ->header('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, X-Token-Auth, Authorization, Origin, Accept');
    }
}

Route::get('/storage/branch_profiles/{filename}', function ($filename) {

    if (
        str_contains($filename, '..')
        || str_contains($filename, 'spa-verification-documents')
    ) {
        abort(404);
    }

    $fullPath = storage_path('app/public/branch_profiles/' . $filename);
    if (!file_exists($fullPath)) {
        $fullPath = storage_path('app/public/' . $filename);
        if (!file_exists($fullPath)) {
            abort(404);
        }
    }

    $file = file_get_contents($fullPath);
    $mimeType = mime_content_type($fullPath);

    return corsResponse(response($file, 200)
        ->header('Content-Type', $mimeType)
        ->header('Content-Length', filesize($fullPath))
        ->header('Cache-Control', 'public, max-age=3600'));
})->where('filename', '.*')->withoutMiddleware(['auth', 'auth:sanctum']);

Route::get('/storage/{path}', function ($path) {

    if (
        str_contains($path, '..')
        || str_contains($path, 'spa-verification-documents')
    ) {
        abort(404);
    }

    $fullPath = storage_path('app/public/' . $path);
    if (!file_exists($fullPath)) abort(404);

    $file = file_get_contents($fullPath);
    $mimeType = mime_content_type($fullPath);

    return corsResponse(response($file, 200)
        ->header('Content-Type', $mimeType)
        ->header('Content-Length', filesize($fullPath))
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept')
        ->header('Cache-Control', 'public, max-age=3600'));
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

    Route::get('/bookings/online/available-slots', [OnlineBookingCheckoutController::class, 'availableSlots'])
        ->name('bookings.online.available-slots');

    Route::get('/bookings/online/payment/success', [OnlineBookingCheckoutController::class, 'success'])
        ->name('bookings.online.payment.success');

    Route::get('/bookings/online/payment/cancel', [OnlineBookingCheckoutController::class, 'cancel'])
        ->name('bookings.online.payment.cancel');
});

Route::post('/webhooks/paymongo', [PaymongoWebhookController::class, 'handle'])
    ->name('webhooks.paymongo');

/*
|--------------------------------------------------------------------------
| Public Job Application (no auth anyone can apply)
|--------------------------------------------------------------------------
*/

Route::post('/apply/{branch}', [PublicApplicationController::class, 'store'])
    ->name('public.apply');

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

    Route::get('/appointments/live-data', [BookingController::class, 'liveData'])
        ->name('appointments.live-data');
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

// CORS headers to these routes that Flutter calls
Route::get('/reschedule-requests/booking/{bookingId}', [RescheduleRequestController::class, 'status'])
    ->middleware('auth')
    ->name('reschedule.flutter.status');

Route::patch('/profile', [ProfileController::class, 'update'])
    ->middleware('auth')
    ->name('profile.update');

/*
|--------------------------------------------------------------------------
| Staff Dashboard
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'verified',
    'force.password.change',
    'owner.onboarding',
    'spa.access',
    \App\Http\Middleware\EnsureBranchOperational::class,
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('role:owner|manager|therapist|receptionist')
        ->name('dashboard');

    Route::get('/dashboard/live-data', [DashboardController::class, 'liveData'])
        ->middleware('role:owner|manager|therapist|receptionist')
        ->name('dashboard.live-data');

    Route::post('/appointments/{booking}/fix-duration', [BookingController::class, 'fixDuration'])
        ->middleware('branch.permission:edit appointments')
        ->name('appointments.fix-duration');

    Route::post('/appointments/fix-all-durations', [BookingController::class, 'fixAllDurations'])
        ->middleware('branch.permission:edit appointments')
        ->name('appointments.fix-all-durations');
});

/*
|--------------------------------------------------------------------------
| Staff: Respond to own deployment (accept/decline)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/branch-deployments/{deployment}/staff-respond', [BranchDeploymentController::class, 'staffRespond'])
        ->name('branch-deployments.staff-respond');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/branch-deployments/self-request', [BranchDeploymentController::class, 'storeSelf'])
        ->name('branch-deployments.self-request');
    Route::delete('/branch-deployments/{deployment}/self-cancel',[BranchDeploymentController::class, 'selfCancel'])
        ->name('branch-deployments.self-cancel');
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

Route::middleware([
    'auth',
    'verified',
    'force.password.change',
    'spa.access',
    \App\Http\Middleware\EnsureBranchOperational::class,
])->group(function () {

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

    Route::middleware('branch.permission:request appointment reassignment')->group(function () {
        Route::post('/appointments/{booking}/reassignment-requests', [ReassignmentRequestController::class, 'store'])
            ->name('reassignment.store');
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
        Route::post('/branch/switch', [BranchController::class, 'switch'])
            ->name('branch.switch');

        Route::get('/branch/current', [BranchController::class, 'getCurrentBranch'])
            ->name('branch.current');

        Route::prefix('branches')->group(function () {
            Route::get('/', [BranchController::class, 'index'])
                ->name('branches.index');

            Route::get('/{branch}', [BranchController::class, 'show'])
                ->name('branches.show');
        });
    });

    Route::middleware([
        'branch.permission:create branches',
        'plan:all_branches',
    ])->group(function () {
        Route::post('/branches', [BranchController::class, 'store'])
            ->name('branches.store');
    });

    // Branch edit page remains open for permitted users.
    Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])
        ->middleware(
            'branch.permission:edit branch general,edit branch hours,edit branch profile'
        )
        ->name('branches.edit');

    Route::middleware('branch.permission:edit branch general')->group(function () {
        Route::put('/branches/{branch}/general', [
            BranchController::class,
            'updateGeneral',
        ])->name('branches.update.general');
    });

    Route::middleware('branch.permission:edit branch hours')->group(function () {
        Route::put('/branches/{branch}/hours', [
            BranchController::class,
            'updateHours',
        ])->name('branches.update.hours');
    });

    Route::middleware('branch.permission:edit branch profile')->group(function () {
        Route::put('/branches/{branch}/profile', [
            BranchController::class,
            'updateProfile',
        ])->name('branches.update.profile');
    });

    Route::middleware([
        'branch.permission:delete branches',
        'plan:all_branches',
    ])->group(function () {
        Route::delete('/branches/{branch}', [
            BranchController::class,
            'destroy',
        ])->name('branches.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Services / Treatments / Packages
    |--------------------------------------------------------------------------
    */
    Route::middleware('branch.permission:view services')->group(function () {
        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');

        Route::get('/treatments/{treatment}/recipe', [TreatmentRecipeController::class, 'show'])
            ->name('treatments.recipe.show');
    });

    Route::middleware('branch.permission:create treatments,edit treatments,delete treatments,create packages,edit packages,delete packages')->group(function () {
        Route::resource('treatments', TreatmentController::class)->except(['index', 'create', 'edit']);
        Route::resource('packages', PackageController::class)->except(['index', 'create', 'edit']);

        Route::get('/services/treatments/export', [ServiceImportExportController::class, 'exportTreatments'])
            ->name('treatments.export');

        Route::post('/services/treatments/import', [ServiceImportExportController::class, 'importTreatments'])
            ->name('treatments.import');

        Route::get('/services/treatments/sample-csv', [ServiceImportExportController::class, 'sampleTreatmentsCsv'])
            ->name('treatments.sample-csv');

        Route::get('/services/packages/export', [ServiceImportExportController::class, 'exportPackages'])
            ->name('packages.export');

        Route::post('/services/packages/import', [ServiceImportExportController::class, 'importPackages'])
            ->name('packages.import');

        Route::get('/services/packages/sample-csv', [ServiceImportExportController::class, 'samplePackagesCsv'])
            ->name('packages.sample-csv');
    });

    Route::middleware('branch.permission:edit treatments')->group(function () {
        Route::put('/treatments/{treatment}/recipe', [TreatmentRecipeController::class, 'update'])
            ->name('treatments.recipe.update');
    });

    Route::middleware('branch.permission:view promos')->group(function () {
        Route::get('/services/promos', [PromoController::class, 'index'])->name('promos.index');
    });

    Route::middleware('branch.permission:create promos')->group(function () {
        Route::post('/services/promos', [PromoController::class, 'store'])->name('promos.store');
    });

    Route::middleware('branch.permission:edit promos')->group(function () {
        Route::put('/services/promos/{promo}', [PromoController::class, 'update'])->name('promos.update');
    });

    Route::middleware('branch.permission:delete promos')->group(function () {
        Route::delete('/services/promos/{promo}', [PromoController::class, 'destroy'])->name('promos.destroy');
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
        Route::get('/therapist/performance', [TherapistPerformanceController::class, 'index'])
            ->name('therapist.performance');
    });

    /*
    |--------------------------------------------------------------------------
    | Procurement
    |--------------------------------------------------------------------------
    */
    Route::prefix('procurement')->name('procurement.')->group(function () {

        Route::middleware([
            'branch.permission:view suppliers',
            'plan:procurement',
        ])->group(function () {
            Route::get('/suppliers', [\App\Http\Controllers\SupplierController::class, 'index'])
                ->name('suppliers.index');
        });

        Route::middleware([
            'branch.permission:create suppliers',
            'plan:procurement',
        ])->group(function () {
            Route::post('/suppliers', [\App\Http\Controllers\SupplierController::class, 'store'])
                ->name('suppliers.store');
        });

        Route::middleware([
            'branch.permission:edit suppliers',
            'plan:procurement',
        ])->group(function () {
            Route::put('/suppliers/{supplier}', [\App\Http\Controllers\SupplierController::class, 'update'])
                ->name('suppliers.update');

            Route::patch('/suppliers/{supplier}/status', [\App\Http\Controllers\SupplierController::class, 'updateStatus'])
                ->name('suppliers.status');
        });

        Route::middleware([
            'branch.permission:manage supplier products',
            'plan:procurement',
        ])->group(function () {
            Route::post('/suppliers/{supplier}/products', [\App\Http\Controllers\SupplierController::class, 'attachProduct'])
                ->name('suppliers.products.store');

            Route::put('/suppliers/{supplier}/products/{supplierProduct}', [\App\Http\Controllers\SupplierController::class, 'updateProduct'])
                ->name('suppliers.products.update');
        });

        Route::middleware([
            'branch.permission:view purchase requests',
            'plan:procurement',
        ])->group(function () {
            Route::get('/purchase-requests', [
                PurchaseRequestController::class,
                'index'
            ])->name('purchase-requests.index');
        });

        Route::middleware([
            'branch.permission:create purchase requests',
            'plan:procurement',
        ])->group(function () {
            Route::post('/purchase-requests', [
                PurchaseRequestController::class,
                'store'
            ])->name('purchase-requests.store');
        });

        Route::middleware([
            'branch.permission:review purchase requests',
            'plan:procurement',
        ])->group(function () {
            Route::patch('/purchase-requests/{purchaseRequest}/approve', [
                PurchaseRequestController::class,
                'approve'
            ])->name('purchase-requests.approve');

            Route::patch('/purchase-requests/{purchaseRequest}/reject', [
                PurchaseRequestController::class,
                'reject'
            ])->name('purchase-requests.reject');
        });

    });

    /*
    |--------------------------------------------------------------------------
    | Procurement: Purchase Orders
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'branch.permission:view purchase orders',
        'plan:procurement',
    ])->group(function () {
        Route::get('/procurement/purchase-orders', [PurchaseOrderController::class, 'index'])
            ->name('procurement.purchase-orders.index');
    });

    Route::middleware([
        'branch.permission:create purchase orders',
        'plan:procurement',
    ])->group(function () {
        Route::post('/procurement/purchase-orders', [PurchaseOrderController::class, 'store'])
            ->name('procurement.purchase-orders.store');
    });

    Route::middleware([
        'branch.permission:manage purchase orders',
        'plan:procurement',
    ])->group(function () {
        Route::patch('/procurement/purchase-orders/{purchaseOrder}/issue', [PurchaseOrderController::class, 'issue'])
            ->name('procurement.purchase-orders.issue');

        Route::patch('/procurement/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->name('procurement.purchase-orders.cancel');
    });

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */

    Route::prefix('inventory')->name('inventory.')->group(function () {

        Route::middleware([
            'branch.permission:view inventory',
            'plan:inventory_basic',
        ])->group(function () {
            Route::get('/products', [\App\Http\Controllers\InventoryController::class, 'products'])
                ->name('products');
        });

        Route::middleware([
            'branch.permission:view inventory',
            'plan:inventory_full',
        ])->group(function () {
            Route::get('/batches', [\App\Http\Controllers\InventoryController::class, 'batches'])
                ->name('batches');
        });

        Route::middleware([
            'branch.permission:view inventory logs',
            'plan:inventory_basic',
        ])->group(function () {
            Route::get('/logs', [\App\Http\Controllers\InventoryController::class, 'logs'])
                ->name('logs');

            Route::get('/logs/export-pdf', [\App\Http\Controllers\InventoryController::class, 'exportLogsPdf'])
                ->name('logs.export-pdf');
        });

        Route::middleware([
            'branch.permission:create inventory items',
            'plan:inventory_basic',
        ])->group(function () {
            Route::post('/products', [\App\Http\Controllers\InventoryController::class, 'store'])
                ->name('products.store');

            Route::post('/products/import', [InventoryImportExportController::class, 'importProducts'])
                ->name('products.import');

            Route::get('/products/sample-csv', [InventoryImportExportController::class, 'sampleCsv'])
                ->name('products.sample-csv');
        });

        Route::middleware([
            'branch.permission:edit inventory items',
            'plan:inventory_basic',
        ])->group(function () {
            Route::post('/products/{product}/deduct', [\App\Http\Controllers\InventoryController::class, 'deduct'])
                ->name('products.deduct');

            Route::put('/products/{product}', [\App\Http\Controllers\InventoryController::class, 'update'])
                ->name('products.update');

            Route::post('/products/{product}/adjust-stock', [\App\Http\Controllers\InventoryController::class, 'adjustStock'])
                ->name('products.adjust-stock');

            Route::post('/products/{product}/receive-stock', [\App\Http\Controllers\InventoryController::class, 'receiveStock'])
                ->name('products.receive-stock');

            Route::post('/batches/{batch}/record-loss', [\App\Http\Controllers\InventoryController::class, 'recordBatchLoss'])
                ->name('batches.record-loss');

            Route::get('/products/export', [InventoryImportExportController::class, 'exportProducts'])
                ->name('products.export');
        });

        Route::middleware([
            'branch.permission:delete inventory items',
            'plan:inventory_basic',
        ])->group(function () {
            Route::delete('/products/{product}', [\App\Http\Controllers\InventoryController::class, 'destroy'])
                ->name('products.destroy');
        });

        Route::middleware([
            'branch.permission:view stock transfers',
            'plan:inventory_full',
        ])->group(function () {
            Route::get('/transfers', [StockTransferController::class, 'index'])
                ->name('transfers');
        });

        Route::middleware([
            'branch.permission:create stock transfers',
            'plan:inventory_full',
        ])->group(function () {
            Route::post('/transfers', [StockTransferController::class, 'store'])
                ->name('transfers.store');
        });

        Route::middleware([
            'branch.permission:process stock transfers',
            'plan:inventory_full',
        ])->group(function () {
            Route::post('/transfers/{transfer}/process', [StockTransferController::class, 'process'])
                ->name('transfers.process');
        });

        Route::middleware([
            'branch.permission:cancel stock transfers',
            'plan:inventory_full',
        ])->group(function () {
            Route::post('/transfers/{transfer}/cancel', [StockTransferController::class, 'cancel'])
                ->name('transfers.cancel');
        });

        Route::middleware([
            'branch.permission:view replenishment',
            'plan:inventory_full',
        ])->group(function () {
            Route::get('/replenishment',[ReplenishmentController::class, 'index']
            )->name('replenishment.index');
        });

        /*
        |--------------------------------------------------------------------------
        | Goods Receipts / GRN
        |--------------------------------------------------------------------------
        */

        Route::middleware([
            'branch.permission:view goods receipts',
            'plan:inventory_full',
        ])->group(function () {
            Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])
                ->name('goods-receipts.index');
        });

        Route::middleware([
            'branch.permission:receive goods',
            'plan:inventory_full'
        ])->group(function () {
            Route::post(
                '/goods-receipts/{purchaseOrder}',
                [GoodsReceiptController::class, 'store']
            )->name('goods-receipts.store');
        });

    });

    /*
    |--------------------------------------------------------------------------
    | Insights
    |--------------------------------------------------------------------------
    */
    Route::middleware([
        'branch.permission:view decision support',
        'plan:insights',
    ])->group(function () {
        Route::get('/decision-support', [DecisionSupportController::class, 'index'])
            ->name('decision-support.index');
    });

    Route::middleware([
        'branch.permission:view reports',
        'plan:insights'
    ])->group(function () {
        Route::get('/reports', [ReportsController::class, 'index'])
            ->name('reports.index');
    });

    /*
    |--------------------------------------------------------------------------
    | HR Modules
    |--------------------------------------------------------------------------
    */
    // Hiring
    Route::middleware([
        'branch.permission:view hiring',
        'plan:manpower',
    ])->group(function () {
        Route::get('/hiring', [HiringController::class, 'index'])
            ->name('hiring.index');
    });

    Route::middleware([
        'branch.permission:create hiring',
        'plan:manpower',
    ])->group(function () {
        Route::post('/hiring', [HiringController::class, 'store'])
            ->name('hiring.store');
    });

    Route::middleware([
        'branch.permission:edit hiring',
        'plan:manpower',
    ])->group(function () {
        Route::put('/hiring/{posting}', [HiringController::class, 'update'])
            ->name('hiring.update');
    });

    Route::middleware([
        'branch.permission:delete hiring',
        'plan:manpower',
    ])->group(function () {
        Route::delete('/hiring/{posting}', [HiringController::class, 'destroy'])
            ->name('hiring.destroy');
    });

    Route::get(
        '/applications/{applicant}/resume',
        [HiringController::class, 'viewResume']
    )->middleware([
        'branch.permission:view applications',
        'plan:manpower',
    ])->name('applications.resume.view');

    // Applications
    Route::middleware([
        'branch.permission:view applications',
        'plan:manpower',
    ])->group(function () {
        Route::get('/applications', [ApplicationController::class, 'index'])
            ->name('applications.index');
    });

    Route::post(
        '/applications/{applicant}/schedule-interview',
        [ApplicationController::class, 'scheduleInterview']
    )->middleware([
        'branch.permission:edit applications',
        'branch.permission:create interviews',
        'plan:manpower',
    ])->name('applications.schedule-interview');

    // Interviews
    Route::middleware([
        'branch.permission:view interviews',
        'plan:manpower',
    ])->group(function () {
        Route::get('/interviews', [InterviewController::class, 'index'])
            ->name('interviews.index');
    });

    Route::middleware([
        'branch.permission:edit interviews',
        'plan:manpower',
    ])->group(function () {
        Route::post(
            '/interviews/{interview}/approve',
            [InterviewController::class, 'approve']
        )->name('interviews.approve');

        Route::post(
            '/interviews/{interview}/reject',
            [InterviewController::class, 'reject']
        )->name('interviews.reject');
    });

    Route::post(
        '/interviews/{interview}/create-staff',
        [InterviewController::class, 'createStaff']
    )->middleware([
        'branch.permission:edit interviews',
        'branch.permission:create staff',
        'plan:manpower',
    ])->name('interviews.create-staff');

    // Deployment
    Route::middleware([
        'branch.permission:view deployments',
        'plan:manpower',
    ])->group(function () {
        Route::get('/deployment', [BranchDeploymentController::class, 'index'])
            ->name('deployment.index');
    });

    Route::middleware([
        'branch.permission:create deployments',
        'plan:manpower',
    ])->group(function () {
        Route::post('/branch-deployments', [BranchDeploymentController::class, 'store'])
            ->name('branch-deployments.store');
    });

    Route::middleware([
        'branch.permission:approve deployments',
        'plan:manpower',
    ])->group(function () {
        Route::post(
            '/branch-deployments/{deployment}/approve',
            [BranchDeploymentController::class, 'approve']
        )->name('branch-deployments.approve');

        Route::post(
            '/branch-deployments/{deployment}/reject',
            [BranchDeploymentController::class, 'reject']
        )->name('branch-deployments.reject');

    });

    Route::middleware([
        'branch.permission:delete deployments',
        'plan:manpower',
    ])->group(function () {
        Route::post(
            '/branch-deployments/{deployment}/cancel',
            [BranchDeploymentController::class, 'cancel']
        )->name('branch-deployments.cancel');
    });

    // Attendance & Leave
    Route::middleware('branch.permission:view attendance,view leave requests,create leave requests,edit leave requests,delete leave requests')->group(function () {
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    });

    // Self-service clock in/out identity-based, not permission-gated.
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');

    Route::middleware('branch.permission:edit attendance')->group(function () {
        Route::post('/attendance/{staff}/record', [AttendanceController::class, 'recordFor'])->name('attendance.record');
    });

    // Leave Requests
    Route::middleware('branch.permission:create leave requests')->group(function () {
        Route::post('/leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    });
    Route::get('/leave-requests/mine', [LeaveRequestController::class, 'mine'])->name('leave-requests.mine');
    // Not permission-gated at the route level reachable by the leave's own
    // submitter (for their post-submit preview) or an approver (for the
    // review modal's inline picker). affectedBookings() enforces the real
    // ownership-or-approver check itself.
    Route::get('/leave-requests/{leaveRequest}/affected-bookings', [LeaveRequestController::class, 'affectedBookings'])->name('leave-requests.affected-bookings');
    Route::middleware('branch.permission:edit leave requests')->group(function () {
        Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
        Route::post('/leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
        Route::post('/leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
    });

    // Payroll  Owner and HR only, via `view payroll` / `edit payroll`.
    // In each group, static URIs are registered before parameterised ones so a
    // static segment is never captured as a parameter (/payroll/runs/preview must
    // come before /payroll/runs/{run}).
    Route::middleware([
        'branch.permission:view payroll',
        'plan:payroll',
    ])->group(function () {
        // Static
        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::get('/payroll/runs/preview', [PayrollController::class, 'preview'])->name('payroll.runs.preview');
        Route::get('/payroll/setup', [PayrollSetupController::class, 'index'])->name('payroll.setup.index');
        Route::get('/payroll/setup/commission-rules/preview', [PayrollSetupController::class, 'previewRule'])->name('payroll.setup.rules.preview');
        Route::get('/payroll/staff', [StaffPayProfileController::class, 'index'])->name('payroll.staff.index');

        // Parameterised
        Route::get('/payroll/runs/{run}', [PayrollController::class, 'show'])->name('payroll.runs.show');
        Route::get('/payroll/runs/{run}/print', [PayrollController::class, 'print'])->name('payroll.runs.print');
        Route::get('/payroll/payslips/{payslip}', [PayslipController::class, 'show'])->name('payroll.payslips.show');
        Route::get('/payroll/staff/{staff}', [StaffPayProfileController::class, 'show'])->name('payroll.staff.show');
    });

    Route::middleware([
        'branch.permission:edit payroll',
        'plan:payroll',
    ])->group(function () {
        // Static
        Route::post('/payroll/runs', [PayrollController::class, 'store'])->name('payroll.runs.store');
        Route::put('/payroll/setup/schedule', [PayrollSetupController::class, 'updateSchedule'])->name('payroll.setup.schedule');
        Route::post('/payroll/setup/commission-rules', [PayrollSetupController::class, 'storeRule'])->name('payroll.setup.rules.store');

        // Parameterised runs, payslips and manual adjustment lines (Unit 6)
        Route::post('/payroll/runs/{run}/regenerate', [PayrollController::class, 'regenerate'])->name('payroll.runs.regenerate');
        Route::delete('/payroll/runs/{run}', [PayrollController::class, 'destroy'])->name('payroll.runs.destroy');
        Route::post('/payroll/runs/{run}/approve', [PayrollController::class, 'approve'])->name('payroll.runs.approve');
        Route::post('/payroll/runs/{run}/send-back', [PayrollController::class, 'sendBack'])->name('payroll.runs.send-back');
        Route::post('/payroll/runs/{run}/finalize', [PayrollController::class, 'finalize'])->name('payroll.runs.finalize');
        Route::post('/payroll/runs/{run}/release', [PayrollController::class, 'release'])->name('payroll.runs.release');
        Route::post('/payroll/payslips/{payslip}/lines', [PayslipController::class, 'storeLine'])->name('payroll.payslips.lines.store');
        Route::delete('/payroll/lines/{line}', [PayslipController::class, 'destroyLine'])->name('payroll.lines.destroy');

        // Parameterised payroll setup and staff pay (Unit 5)
        Route::put('/payroll/setup/branches/{branch}/wage', [PayrollSetupController::class, 'updateBranchWage'])->name('payroll.setup.wages.update');
        Route::put('/payroll/setup/commission-rules/{rule}', [PayrollSetupController::class, 'updateRule'])->name('payroll.setup.rules.update');
        Route::post('/payroll/setup/commission-rules/{rule}/change', [PayrollSetupController::class, 'supersedeRule'])->name('payroll.setup.rules.supersede');
        Route::post('/payroll/setup/commission-rules/{rule}/end', [PayrollSetupController::class, 'endRule'])->name('payroll.setup.rules.end');
        Route::delete('/payroll/setup/commission-rules/{rule}', [PayrollSetupController::class, 'destroyRule'])->name('payroll.setup.rules.destroy');

        Route::post('/payroll/staff/{staff}/profiles', [StaffPayProfileController::class, 'storeProfile'])->name('payroll.staff.profiles.store');
        Route::put('/payroll/staff/{staff}/profiles/{profile}', [StaffPayProfileController::class, 'updateProfile'])->name('payroll.staff.profiles.update');
        Route::delete('/payroll/staff/{staff}/profiles/{profile}', [StaffPayProfileController::class, 'destroyProfile'])->name('payroll.staff.profiles.destroy');
        Route::post('/payroll/staff/{staff}/statutory-ids/reveal', [StaffPayProfileController::class, 'revealStatutoryIds'])->name('payroll.staff.ids.reveal');
        Route::put('/payroll/staff/{staff}/statutory-ids', [StaffPayProfileController::class, 'updateStatutoryIds'])->name('payroll.staff.ids.update');
        Route::post('/payroll/staff/{staff}/recurring', [StaffPayProfileController::class, 'storeRecurring'])->name('payroll.staff.recurring.store');
        Route::post('/payroll/staff/{staff}/recurring/{item}/stop', [StaffPayProfileController::class, 'stopRecurring'])->name('payroll.staff.recurring.stop');
        Route::delete('/payroll/staff/{staff}/recurring/{item}', [StaffPayProfileController::class, 'destroyRecurring'])->name('payroll.staff.recurring.destroy');
    });

    // My Payslips (Unit 6) identity-based self-service, deliberately not
    // permission-gated. MyPayslipController limits it to the signed-in user's own
    // Staff records and answers 404 for anyone else's payslip. Printing is the
    // detail page's own Print button (window.print()), so there is no print route.
    Route::get('/my-payslips', [MyPayslipController::class, 'index'])->name('my-payslips.index');
    Route::get('/my-payslips/{payslip}', [MyPayslipController::class, 'show'])->name('my-payslips.show');

    /*
    |--------------------------------------------------------------------------
    | Finance Modules
    |--------------------------------------------------------------------------
    */
    Route::middleware([
        'branch.permission:view revenue',
        'plan:finance',
    ])->group(function () {
        Route::get('/revenue', [RevenueController::class, 'index'])->name('revenue.index');
    });

    Route::middleware([
        'branch.permission:view billing',
        'plan:finance',
    ])->group(function () {
        Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    });

    Route::middleware([
        'branch.permission:create billing',
        'plan:finance',
    ])->group(function () {
        Route::post('/billing/expenses', [BillingController::class, 'storeExpense'])
            ->name('billing.expense.store');
    });

    Route::middleware([
        'branch.permission:edit billing',
        'plan:finance',
    ])->group(function () {
        Route::patch('/billing/expenses/{expense}/status', [BillingController::class, 'updateExpenseStatus'])
            ->name('billing.expense.updateStatus');
    });

    Route::middleware([
        'branch.permission:view vendor bills',
        'plan:finance',
    ])->group(function () {
        Route::get('/vendor-bills', [VendorBillController::class, 'index'])
            ->name('vendor-bills.index');
    });

    Route::middleware([
        'branch.permission:create vendor bills',
        'plan:finance',
    ])->group(function () {
        Route::post('/vendor-bills', [VendorBillController::class, 'store'])
            ->name('vendor-bills.store');
    });

    Route::middleware([
        'branch.permission:match vendor bills',
        'plan:finance',
    ])->group(function () {
        Route::post('/vendor-bills/{vendorBill}/match',[VendorBillController::class, 'match'])
            ->name('vendor-bills.match');
    });

    Route::middleware([
        'branch.permission:edit vendor bills',
        'plan:finance',
    ])->group(function () {
        Route::put('/vendor-bills/{vendorBill}',[VendorBillController::class, 'update'])
            ->name('vendor-bills.update');
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
            Route::get('/subscriptions', [AdminSubscriptionController::class, 'index'])->name('subscriptions.index');
        });

        Route::middleware('permission:edit registered spas')->group(function () {
            Route::get('/registered-spas/{spa}/edit', [RegisteredSpaController::class, 'edit'])->name('registered-spas.edit');
            Route::put('/registered-spas/{spa}', [RegisteredSpaController::class, 'update'])->name('registered-spas.update');
            Route::delete('/registered-spas/{spa}', [RegisteredSpaController::class, 'destroy'])->name('registered-spas.destroy');
            Route::get('/branch-verifications/{branch}/edit', [BranchVerificationController::class, 'edit'])->name('branch-verifications.edit');
            Route::put('/branch-verifications/{branch}', [BranchVerificationController::class, 'update'])->name('branch-verifications.update');
            Route::get('/document-reviews/{document}/edit', [DocumentReviewController::class, 'edit'])->name('document-reviews.edit');
            Route::put('/document-reviews/{document}', [DocumentReviewController::class, 'update'])->name('document-reviews.update');
        });

        Route::middleware('permission:view registered users')->group(function () {
            Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        });

        Route::middleware('permission:edit registered users')->group(function () {
            Route::put('/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.updateRole');
        });

        Route::middleware('permission:delete registered users')->group(function () {
            Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
            Route::post('/users/{id}/restore', [UserManagementController::class, 'restore'])->name('users.restore');
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

Route::middleware(['auth', 'owner-only', 'owner.onboarding'])->group(function () {
    Route::get('/setup/index', [SetupController::class, 'index'])->name('setup.index');
    Route::post('/setup/spa', [SetupController::class, 'storeSpa'])->name('setup.store-spa');

    Route::get('/setup/branches', [SetupController::class, 'branches'])->name('setup.branches');
    Route::post('/setup/branches', [SetupController::class, 'storeBranch'])->name('setup.store-branch');
    Route::get('/setup/documents', [SetupController::class, 'documents'])->name('setup.documents');
});

/*
|--------------------------------------------------------------------------
| Owner Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:owner', 'owner.onboarding'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        // Spa Profile
        Route::get('/spa-profile', [SpaProfileController::class, 'edit'])->name('spa-profile.edit');
        Route::patch('/spa-profile', [SpaProfileController::class, 'update'])->name('spa-profile.update');
        Route::post('/spa-profile/documents', [SpaProfileController::class, 'uploadDocument'])->name('spa-profile.documents.upload');
        Route::delete('/spa-profile/documents/{document}', [SpaProfileController::class, 'destroyDocument'])->name('spa-profile.documents.destroy');
        Route::post('/spa-profile/branches/{branch}/documents', [SpaProfileController::class, 'uploadBranchDocuments'])->name('spa-profile.branches.documents.upload');

        // Roles & Permissions
        Route::get('/roles-permissions', [OwnerRolePermissionController::class, 'index'])->name('roles-permissions.index');
        Route::get('/roles-permissions/{role}/edit', [OwnerRolePermissionController::class, 'edit'])->name('roles-permissions.edit');
        Route::put('/roles-permissions/{role}', [OwnerRolePermissionController::class, 'update'])->name('roles-permissions.update');

        // Free trial selection
        Route::get('/trial/choose', [TrialController::class, 'choose'])
            ->name('trial.choose');

        Route::post('/trial/start', [TrialController::class, 'start'])
            ->name('trial.start');

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
    Route::get('/owner/subscription/receipt/{subscription}',[SubscriptionController::class, 'downloadReceipt'])
        ->name('owner.subscription.receipt');
});

Route::middleware(['auth', 'owner-only', 'owner.onboarding'])->group(function () {
    Route::get('/owner/onboarding/waiting', function () {
        return redirect()->route('dashboard');
    })->name('owner.onboarding.waiting');
});

Route::middleware(['auth', 'verified', 'force.password.change'])->group(function () {
    Route::get('/spa/locked', [LockedSpaController::class, 'show'])
        ->name('spa.locked');
});

/*
|--------------------------------------------------------------------------
| Owner/Manager: Reschedule Approvals
|--------------------------------------------------------------------------
*/

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
| Owner/Manager/Receptionist: Reassignment Approvals
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/reassignment-requests', [ReassignmentRequestController::class, 'index'])
        ->middleware('branch.permission:edit appointments')
        ->name('reassignment.index');

    Route::post('/reassignment-requests/{reassignmentRequest}/approve', [ReassignmentRequestController::class, 'approve'])
        ->middleware('branch.permission:edit appointments')
        ->name('reassignment.approve');

    Route::post('/reassignment-requests/{reassignmentRequest}/reject', [ReassignmentRequestController::class, 'reject'])
        ->middleware('branch.permission:edit appointments')
        ->name('reassignment.reject');
});


/*
|--------------------------------------------------------------------------
| Verification Documents (private, owner of the spa or admin only)
|--------------------------------------------------------------------------
*/

Route::get('/verification-documents/{document}', [VerificationDocumentController::class, 'show'])
    ->middleware('auth')
    ->name('verification-documents.show');

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

Route::get('/web-api/spas/nearby', [LandingController::class, 'nearbySpasList']);
Route::get('/web-api/spas/search', [LandingController::class, 'searchSpas']);
Route::get('/web-api/spas/{spaId}/{branchId}/reviews', [LandingController::class, 'spaReviews']);

require __DIR__.'/auth.php';

