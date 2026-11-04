<?php

namespace Tests\Feature\Payroll\Concerns;

use App\Http\Controllers\HR\PayrollSetupController;
use App\Http\Controllers\HR\StaffPayProfileController;
use Illuminate\Support\Facades\Route;

/**
 * Unit 5 routes as expected in Unit 7 (web.php is not edited in Unit 5). Registered
 * here only when web.php does not define them yet, so these tests keep working
 * unchanged after Unit 7 adds the real routes.
 */
trait PayrollSetupRoutes
{
    protected function registerPayrollSetupRoutes(): void
    {
        if (Route::has('payroll.setup.index')) {
            return;
        }

        Route::middleware(['web', 'auth'])->group(function () {
            Route::middleware('branch.permission:view payroll')->group(function () {
                Route::get('/payroll/setup', [PayrollSetupController::class, 'index'])->name('payroll.setup.index');
                Route::get('/payroll/setup/commission-rules/preview', [PayrollSetupController::class, 'previewRule'])->name('payroll.setup.rules.preview');
                Route::get('/payroll/staff', [StaffPayProfileController::class, 'index'])->name('payroll.staff.index');
                Route::get('/payroll/staff/{staff}', [StaffPayProfileController::class, 'show'])->name('payroll.staff.show');
            });

            Route::middleware('branch.permission:edit payroll')->group(function () {
                Route::put('/payroll/setup/schedule', [PayrollSetupController::class, 'updateSchedule'])->name('payroll.setup.schedule');
                Route::put('/payroll/setup/branches/{branch}/wage', [PayrollSetupController::class, 'updateBranchWage'])->name('payroll.setup.wages.update');
                Route::post('/payroll/setup/commission-rules', [PayrollSetupController::class, 'storeRule'])->name('payroll.setup.rules.store');
                Route::put('/payroll/setup/commission-rules/{rule}', [PayrollSetupController::class, 'updateRule'])->name('payroll.setup.rules.update');
                Route::post('/payroll/setup/commission-rules/{rule}/change', [PayrollSetupController::class, 'supersedeRule'])->name('payroll.setup.rules.supersede');
                Route::post('/payroll/setup/commission-rules/{rule}/end', [PayrollSetupController::class, 'endRule'])->name('payroll.setup.rules.end');
                Route::delete('/payroll/setup/commission-rules/{rule}', [PayrollSetupController::class, 'destroyRule'])->name('payroll.setup.rules.destroy');

                Route::post('/payroll/staff/{staff}/profiles', [StaffPayProfileController::class, 'storeProfile'])->name('payroll.staff.profiles.store');
                Route::put('/payroll/staff/{staff}/profiles/{profile}', [StaffPayProfileController::class, 'updateProfile'])->name('payroll.staff.profiles.update');
                Route::delete('/payroll/staff/{staff}/profiles/{profile}', [StaffPayProfileController::class, 'destroyProfile'])->name('payroll.staff.profiles.destroy');
                Route::put('/payroll/staff/{staff}/statutory-ids', [StaffPayProfileController::class, 'updateStatutoryIds'])->name('payroll.staff.ids.update');
                Route::post('/payroll/staff/{staff}/recurring', [StaffPayProfileController::class, 'storeRecurring'])->name('payroll.staff.recurring.store');
                Route::post('/payroll/staff/{staff}/recurring/{item}/stop', [StaffPayProfileController::class, 'stopRecurring'])->name('payroll.staff.recurring.stop');
                Route::delete('/payroll/staff/{staff}/recurring/{item}', [StaffPayProfileController::class, 'destroyRecurring'])->name('payroll.staff.recurring.destroy');
            });
        });

        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }
}
