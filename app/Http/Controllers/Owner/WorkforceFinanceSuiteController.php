<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkforceFinanceSuiteController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $spa = $user->spa;

        abort_unless($user->hasRole('owner'), 403);
        abort_unless($spa, 403, 'No spa assigned to this account.');

        $branches = $spa->branches()
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        return view('owner.workforce-finance-suite.index', compact('spa', 'branches'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $spa = $user->spa;

        abort_unless($user->hasRole('owner'), 403);
        abort_unless($spa, 403, 'No spa assigned to this account.');

        if (($spa->business_tier ?? null) !== 'professional') {
            return redirect()
                ->route('owner.workforce-finance-suite.index')
                ->with('success', 'Upgrade to Professional first to use this suite.');
        }

        $validated = $request->validate([
            'branches' => ['nullable', 'array'],
            'branches.*' => ['nullable', 'boolean'],
        ]);

        $branchFlags = $validated['branches'] ?? [];

        $branches = $spa->branches()->get();

        foreach ($branches as $branch) {
            $branch->update([
                'has_workforce_finance_suite' => (bool) ($branchFlags[$branch->id] ?? false),
            ]);
        }

        return redirect()
            ->route('owner.workforce-finance-suite.index')
            ->with('success', 'Workforce & Finance Suite settings updated successfully.');
    }
}
