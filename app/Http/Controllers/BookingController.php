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
        $user = Auth::user();

        if ($user->hasRole('owner')) {
            $branchId = session('current_branch_id') ?? $user->branches()->first()->id;
            $therapists = User::role('therapist')
                ->whereHas('staff', function($q) use ($user, $branchId) {
                    $q->where('spa_id', $user->spa_id)
                    ->where('branch_id', $branchId)
                    ->where('employment_status', 'active');
                })
                ->orderBy('name')
                ->get();
        } else {
            // Managers or therapists: only show their own branch
            $therapists = User::role('therapist')
                ->whereHas('staff', function($q) use ($user) {
                    $q->where('spa_id', $user->spa_id)
                    ->where('branch_id', $user->branch_id)
                    ->where('employment_status', 'active');
                })
                ->orderBy('name')
                ->get();
        }

        return view('booking', compact('therapists'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        // Determine spa_id and branch_id
        $spaId = $user->spa_id; // same as before
        $branchId = $user->currentBranchId();

        if (!$branchId) {
            return back()->with('error', 'No branch selected.');
        }

        $validated = $request->validate([
            'service_type' => 'required',
            'treatment'    => 'required',
            'therapist_id' => 'required|exists:users,id',
            'customer_phone'   => 'required',
            'customer_name'    => 'required',
            'customer_address' => 'required',
            'customer_email'   => 'required|email',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required',
        ]);

        // Check therapist availability within the same spa & branch
        $exists = Booking::where('appointment_date', $validated['appointment_date'])
            ->where('appointment_time', $validated['appointment_time'])
            ->where('therapist_id', $validated['therapist_id'])
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['reserved', 'confirmed'])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'appointment_time' => 'This therapist is already booked for this time.'
            ])->withInput();
        }

        Booking::create([
            ...$validated,
            'spa_id' => $spaId,
            'branch_id' => $branchId,
            'created_by_user_id' => $user->id,
            'status' => 'reserved',
        ]);

        return redirect()
            ->route('booking')
            ->with('success', 'Booking reserved successfully!');
    }

    // ADMIN VIEW WITH PAGINATION
    public function adminIndex()
    {
        $user = Auth::user();

        $query = Booking::with('therapist', 'branch');

        if ($user->hasRole('owner')) {
            $branchId = session('current_branch_id') ?? null;
            $query->where('spa_id', $user->spa_id);
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
        } elseif ($user->hasRole('manager')) {
            $query->where('spa_id', $user->spa_id)
                ->where('branch_id', $user->branch_id);
        } elseif ($user->hasRole('therapist')) {
            $query->where('spa_id', $user->spa_id)
                ->where('branch_id', $user->branch_id)
                ->where('therapist_id', $user->id);
        }

        $bookings = $query->orderBy('appointment_date', 'asc')
                        ->orderBy('appointment_time', 'asc')
                        ->paginate(10);

        $therapists = User::role('therapist')
                        ->whereHas('staff', function($q) use ($user) {
                            $q->where('spa_id', $user->spa_id);
                        })
                        ->orderBy('name')
                        ->get();

        return view('appointments', compact('bookings', 'therapists'));
    }

    public function edit(Booking $booking)
    {
        // Get therapists from Staff model - KEEP THIS ONE
        $user = Auth::user();

        $therapistsQuery = User::role('therapist')->where('status', 'active');

        if ($user->hasRole('owner')) {
            // Owner: all therapists in their spa
            $therapistsQuery->where('spa_id', $user->spa_id);
        } else {
            // Manager/Receptionist/Therapist: only their branch
            $therapistsQuery->where('branch_id', $user->branch_id)
                            ->where('spa_id', $user->spa_id);
        }

        $therapists = $therapistsQuery->orderBy('name')->get();

        return view('appointments.edit', compact('booking', 'therapists'));
    }

    public function update(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email',
            'service_type' => 'required|string',
            'treatment' => 'required|string',
            'therapist_id' => 'required|exists:users,id',
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
