<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;

class BillingController extends Controller
{
    public function index()
    {
        return view('finance.billing.index');
    }
}