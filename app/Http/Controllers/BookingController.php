<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_type' => 'required',
            'treatment'    => 'required',
            'therapist_id' => 'required',       // therapist_id (foreign key)
            'customer_phone'   => 'required',
            'customer_name'    => 'required',
            'customer_address' => 'required',
            'customer_email'   => 'required|email',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required',
        ]);

        $exists = Booking::where('appointment_date', $validated['appointment_date'])
            ->where('appointment_time', $validated['appointment_time'])
            ->where('therapist_id', $validated['therapist_id'])
            ->whereIn('status', ['reserved', 'confirmed'])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'appointment_time' => 'This therapist is already booked for this time.'
            ])->withInput();
        }

        
        Booking::create([
            ...$validated,
            'spa_id' => $request->spa_id ?? 1,
            'branch_id' => $request->branch_id ?? 1,
            'created_by_user_id' => Auth::id(),
            // 'booking_source' => $request->booking_source ?? 'online',
            'status' => 'reserved',
        ]);

        return redirect()
            ->route('booking')
            ->with('success', 'Booking reserved successfully!');
    }

    // ADMIN VIEW WITH PAGINATION
    public function adminIndex()
    {
        $bookings = Booking::orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
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
        $bookings = Booking::orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->paginate(5);

        return view('appointments', compact('bookings'));
    }

    public function destroy($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->delete();

        return redirect()->back()->with('success', 'Appointment deleted successfully!');
    }

    public function create()
    {
        $therapists = User::role('therapist')->get();

        return view('booking', compact('therapists'));
    }
}
