<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;

class BookingController extends Controller
{
    public function index()
    {
        return view('bookings.index');
    }

    public function store(Request $request)
    {
        // VALIDATION
        $validated = $request->validate([
            'service_type' => 'required',
            'treatment'    => 'required',
            'therapist'    => 'required',
            'phone'        => 'required',
            'fullname'     => 'required',
            'address'      => 'required',
            'email'        => 'required|email',
            'date'         => 'required|date',
            'time'         => 'required',
        ]);

        // SAVE BOOKING
        Booking::create($validated);
        $request->session()->flash('success', 'Booking created successfully!');
        // SUCCESS MESSAGE
        return redirect()->back()->with('success', 'Booking submitted successfully');
    }
}
