<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Expense;   // ← You need to create this model + migration (see notes below)
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| REQUIRED: Create the Expense model and migration before using this controller.
|
| Run:  php artisan make:model Expense -m
|
| Migration columns needed:
|   $table->id();
|   $table->foreignId('spa_id')->constrained();
|   $table->foreignId('branch_id')->constrained();
|   $table->foreignId('requested_by')->constrained('users');  // staff who filed it
|   $table->foreignId('reviewed_by')->nullable()->constrained('users');
|   $table->string('title');                   // short label / request name
|   $table->text('description')->nullable();   // detailed description
|   $table->decimal('amount', 10, 2);
|   $table->enum('status', ['pending', 'on_review', 'accepted', 'rejected'])->default('pending');
|   $table->text('review_notes')->nullable();  // notes given on review/rejection
|   $table->timestamp('reviewed_at')->nullable();
|   $table->softDeletes();
|   $table->timestamps();
|--------------------------------------------------------------------------
*/

class BillingController extends Controller
{
    private function getSpaAndBranch(): array
    {
        $user = Auth::user();
        return [$user->spa, $user->currentBranchId()];
    }

    public function index(Request $request)
    {
        [$spa, $branchId] = $this->getSpaAndBranch();

        $from = $request->filled('from')
            ? Carbon::parse($request->from)->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfMonth();

        // ── Billing Table ───────────────────────────────────────────────────
        // Billing = all non-cancelled bookings (regardless of status),
        // showing what customers owe or have paid.
        $billingRecords = Booking::where('spa_id', $spa->id)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', ['cancelled'])
            ->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])
            ->with('therapist')
            ->latest('appointment_date')
            ->get();

        $billingTotal     = $billingRecords->sum('total_amount');
        $billingCollected = $billingRecords->sum('amount_paid');
        $billingBalance   = $billingRecords->sum('balance_amount');

        // ── Expenses Table ──────────────────────────────────────────────────
        // Expense model required — see migration notes above.
        // If the model doesn't exist yet, comment this block out.
        $expenseQuery = Expense::where('spa_id', $spa->id)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->with(['requester', 'reviewer'])
            ->whereBetween('created_at', [$from, $to]);

        // Optional: filter by status
        if ($request->filled('expense_status')) {
            $expenseQuery->where('status', $request->expense_status);
        }

        $expenses = $expenseQuery->latest()->get();

        $expenseTotalAmount   = $expenses->sum('amount');
        $expensesAccepted     = $expenses->where('status', 'accepted')->sum('amount');
        $expensesPending      = $expenses->whereIn('status', ['pending', 'on_review'])->count();

        return view('finance.billing.index', [
            'from'             => $from,
            'to'               => $to,
            // Billing
            'billingRecords'   => $billingRecords,
            'billingTotal'     => $billingTotal,
            'billingCollected' => $billingCollected,
            'billingBalance'   => $billingBalance,
            // Expenses
            'expenses'         => $expenses,
            'expenseTotalAmount'  => $expenseTotalAmount,
            'expensesAccepted' => $expensesAccepted,
            'expensesPending'  => $expensesPending,
            'expenseStatusFilter' => $request->expense_status,
        ]);
    }

    // ── Update expense status (accept / reject / set on review) ────────────
    public function updateExpenseStatus(Request $request, Expense $expense)
    {
        $request->validate([
            'status'       => ['required', 'in:on_review,accepted,rejected'],
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $expense->update([
            'status'       => $request->status,
            'review_notes' => $request->review_notes,
            'reviewed_by'  => Auth::id(),
            'reviewed_at'  => now(),
        ]);

        $label = match($request->status) {
            'accepted'  => 'Expense accepted and funds released.',
            'rejected'  => 'Expense rejected.',
            'on_review' => 'Expense marked as under review.',
        };

        return back()->with('success', $label);
    }

    // ── File a new expense request ──────────────────────────────────────────
    public function storeExpense(Request $request)
    {
        [$spa, $branchId] = $this->getSpaAndBranch();

        $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount'      => ['required', 'numeric', 'min:0.01'],
        ]);

        Expense::create([
            'spa_id'       => $spa->id,
            'branch_id'    => $branchId,
            'requested_by' => Auth::id(),
            'title'        => $request->title,
            'description'  => $request->description,
            'amount'       => $request->amount,
            'status'       => 'pending',
        ]);

        return back()->with('success', 'Expense request filed successfully.');
    }
}