<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class LockedSpaController extends Controller
{
    public function show(Request $request): View
    {
        abort_if(
            $request->user()?->hasRole('owner')
            || $request->user()?->hasRole('admin')
            || $request->user()?->hasRole('customer'),
            403
        );

        return view('locked-spa');
    }
}
