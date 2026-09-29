<?php

namespace Tests\Feature\Payroll\Concerns;

use App\Http\Controllers\HR\MyPayslipController;
use App\Http\Controllers\HR\PayrollController;
use App\Http\Controllers\HR\PayslipController;
use Illuminate\Support\Facades\Route;

/**
 * Unit 6 routes as expected in Unit 7 (web.php is not edited in Unit 6). Registered
 * here only when web.php does not define them yet, so these tests keep working after
 * Unit 7 adds the real routes. `payroll.index` already exists in web.php and points
 * at PayrollController@index, which Unit 6 replaces.
 */
trait PayrollRunRoutes
{
    protected function registerPayrollRunRoutes(): void
    {
        if (Route::has('payroll.runs.show')) {
            return;
        }

        Route::middleware(['web', 'auth'])->group(function () {
            Route::middleware('branch.permission:view payroll')->group(function () {
                if (! Route::has('payroll.index')) {
                    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
                }
                Route::get('/payroll/runs/preview', [PayrollController::class, 'preview'])->name('payroll.runs.preview');
                Route::get('/payroll/runs/{run}', [PayrollController::class, 'show'])->name('payroll.runs.show');
                Route::get('/payroll/runs/{run}/print', [PayrollController::class, 'print'])->name('payroll.runs.print');
                Route::get('/payroll/payslips/{payslip}', [PayslipController::class, 'show'])->name('payroll.payslips.show');
            });

            Route::middleware('branch.permission:edit payroll')->group(function () {
                Route::post('/payroll/runs', [PayrollController::class, 'store'])->name('payroll.runs.store');
                Route::post('/payroll/runs/{run}/regenerate', [PayrollController::class, 'regenerate'])->name('payroll.runs.regenerate');
                Route::delete('/payroll/runs/{run}', [PayrollController::class, 'destroy'])->name('payroll.runs.destroy');
                Route::post('/payroll/runs/{run}/approve', [PayrollController::class, 'approve'])->name('payroll.runs.approve');
                Route::post('/payroll/runs/{run}/send-back', [PayrollController::class, 'sendBack'])->name('payroll.runs.send-back');
                Route::post('/payroll/runs/{run}/finalize', [PayrollController::class, 'finalize'])->name('payroll.runs.finalize');
                Route::post('/payroll/runs/{run}/release', [PayrollController::class, 'release'])->name('payroll.runs.release');
                Route::post('/payroll/payslips/{payslip}/lines', [PayslipController::class, 'storeLine'])->name('payroll.payslips.lines.store');
                Route::delete('/payroll/lines/{line}', [PayslipController::class, 'destroyLine'])->name('payroll.lines.destroy');
            });

            // Identity-based — no permission middleware.
            Route::get('/my-payslips', [MyPayslipController::class, 'index'])->name('my-payslips.index');
            Route::get('/my-payslips/{payslip}', [MyPayslipController::class, 'show'])->name('my-payslips.show');
        });

        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }
}
