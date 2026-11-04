<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shown to staff while their spa has no access (trial or plan ended and
 * the grace days passed). Owners go to Subscription & Billing instead.
 */
class LockedSpaController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        abort_if(
            $user?->hasRole('owner')
            || $user?->hasRole('admin')
            || $user?->hasRole('customer'),
            403
        );

        $spa = $user?->spa;

        // The owner renewed while this page was open: go back to work.
        if (! $spa || $spa->hasAccess()) {
            return redirect()->route('dashboard');
        }

        return view('locked-spa', ['spaName' => $spa->name]);
    }
}
