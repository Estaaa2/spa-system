<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Staff;
use App\Models\Branch;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    /**
     * Display a listing of the staff.
     */
    public function index(Request $request)
    {
        $query = Staff::with(['user', 'branch', 'branch.spa'])->latest();

        // Filter by branch if requested
        if ($request->has('branch_id') && $request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        }

        $staff = Staff::with(['user', 'branch'])->latest()->get();
        $branches = Branch::all();

        return view('staff.index', compact('staff', 'branches'));
    }

    /**
     * Store a newly created staff in storage.
     */
   // In StaffController.php - update store method
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'branch_id' => 'required|exists:branches,id',
            'roles' => 'required|in:therapist,receptionist,manager',
        ]);

        $currentUser = Auth::user();

        DB::transaction(function () use ($validated, $currentUser) {

            // 1️⃣ Create user
            $tempPassword = Str::random(12);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($tempPassword),
                'spa_id' => $currentUser->spa_id,
                'branch_id' => $validated['branch_id'],
                'temp_password' => $tempPassword,
                'password_reset_required' => true,
            ]);

            // 2️⃣ Assign role via Spatie
            $user->assignRole($validated['roles']);

            // 3️⃣ Create staff record
            Staff::create([
                'user_id' => $user->id,
                'spa_id' => $currentUser->spa_id,
                'branch_id' => $validated['branch_id'],
                'employment_status' => 'active',
                'hire_date' => now(),
            ]);

            // 4️⃣ Optional: email credentials
            // Mail::to($user->email)->send(...)
        });

        return redirect()
            ->route('staff.index')
            ->with('success', 'Staff member added successfully.');
    }

    /**
     * Display the specified staff member.
     */
    public function show(Staff $staff)
    {
        return response()->json([
            'name' => $staff->name,
            'phone' => $staff->phone,
            'roles' => $staff->roles,
            'status' => $staff->status,
        ]);
    }

    /**
     * Update the specified staff in storage.
     */
    public function update(Request $request, Staff $staff)
    {
        $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'phone' => 'required|string|max:20|unique:staff,phone', // Keep required
        'roles' => 'required|in:therapist,receptionist,manager,admin',
        'status' => 'required|in:active,pending,inactive',
    ]);

        try {
            $staff->update($validated);

            // Update user name if user exists
            if ($staff->user) {
                $staff->user->update([
                    'name' => $validated['name']
                ]);
            }

            return redirect()->route('staff.index')
                ->with('success', 'Staff member updated successfully!');

        } 
        catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error updating staff: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified staff from storage.
     */
    public function destroy(Staff $staff)
    {
        try {
            // Delete associated user
            if ($staff->user) {
                $staff->user->delete();
            }

            $staff->delete();

            return redirect()->route('staff.index')
                ->with('success', 'Staff member deleted successfully!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error deleting staff: ' . $e->getMessage());
        }
    }
}
