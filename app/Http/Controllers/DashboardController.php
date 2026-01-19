<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;

class DashboardController extends Controller
{
    public function index()
    {
        $total = Booking::count();
        $pending = Booking::where('status', 'pending')->count();
        $completed = Booking::where('status', 'reserved')->count();

        return view('dashboard', compact('total', 'pending', 'completed'));
    }
}
