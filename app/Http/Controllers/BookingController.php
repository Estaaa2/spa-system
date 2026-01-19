<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_type' => 'required',
            'treatment'    => 'required',
            'therapist'    => 'required',
            'phone'        => 'required',
            'fullname'     => 'required',
            'address'      => 'required',
            'email'        => 'required|email',
            'date'         => 'required|date|after_or_equal:today',
            'time'         => 'required',
        ]);

        $exists = Booking::where('date', $validated['date'])
            ->where('time', $validated['time'])
            ->where('therapist', $validated['therapist'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['This time slot is already booked.']);
        }

        Booking::create([
            ...$validated,
            'user_id' => Auth::id(),
            'status'  => 'pending',
        ]);

        return redirect()
            ->route('booking')
            ->with('success', 'Booking reserved successfully!');
    }

    // ADMIN VIEW WITH PAGINATION
    public function adminIndex()
    {
        $bookings = Booking::orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->paginate(5); // <-- pagination

        return view('appointments', compact('bookings'));
    }

    public function reserve(Booking $booking)
    {
        if ($booking->status !== 'pending') {
            return back()->withErrors('Booking already processed.');
        }

        $booking->update([
            'status' => 'reserved',
            'therapist' => request('therapist') ?? $booking->therapist,
        ]);

        return back()->with('success', 'Booking reserved successfully.');
    }

    public function history()
    {
        $bookings = Booking::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookings);
    }

    // USER VIEW WITH PAGINATION (if you use this)
    public function index()
    {
        $bookings = Booking::orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->paginate(5);

        return view('appointments.index', compact('bookings'));
    }

    public function destroy($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->delete();

        return redirect()->back()->with('success', 'Appointment deleted successfully!');
    }
}
