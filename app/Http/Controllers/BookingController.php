<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Staff;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function create()
    {
        // Fetch therapists from Staff model
        $therapists = Staff::where('roles', 'therapist')
                           ->where('status', 'active')
                           ->orderBy('name')
                           ->get();

        return view('booking', compact('therapists'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_type' => 'required',
            'treatment'    => 'required',
            'therapist_id' => 'required|exists:staff,id',
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
            'status' => 'reserved',
        ]);

        return redirect()
            ->route('booking')
            ->with('success', 'Booking reserved successfully!');
    }

    // ADMIN VIEW WITH PAGINATION
    public function adminIndex()
    {
        $bookings = Booking::with('therapist')
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->paginate(10);

        // Get therapists from Staff model
        $therapists = Staff::where('roles', 'therapist')
                           ->where('status', 'active')
                           ->orderBy('name')
                           ->get();

        return view('appointments', compact('bookings', 'therapists'));
    }

    public function edit(Booking $booking)
    {
        // Get therapists from Staff model - KEEP THIS ONE
        $therapists = Staff::where('roles', 'therapist')
                           ->where('status', 'active')
                           ->orderBy('name')
                           ->get();

        return view('appointments.edit', compact('booking', 'therapists'));
    }

    public function update(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email',
            'service_type' => 'required|string',
            'treatment' => 'required|string',
            'therapist_id' => 'required|exists:staff,id',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'status' => 'required|string|in:pending,reserved,confirmed,completed,cancelled',
        ]);

        $booking->update($validated);

        return redirect()->route('appointments.index')
            ->with('success', 'Appointment updated successfully.');
    }

    // REMOVE THIS DUPLICATE EDIT METHOD - DELETE THESE LINES:
    /*
    public function edit(Booking $booking)
    {
        // Get therapists for dropdown
        $therapists = User::role('therapist')->get();

        return view('appointments.edit', compact('booking', 'therapists'));
    }
    */

    // Keep your other methods (destroy, history, reserve, etc.) here
    public function destroy($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->delete();

        return redirect()->back()->with('success', 'Appointment deleted successfully!');
    }

    public function history()
    {
        $bookings = Booking::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookings);
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
}
