<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Branch;

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
            'email' => 'required|email',
            'phone' => 'required|string|max:20|unique:staff,phone',
            'branch_id' => 'required|exists:branches,id',
            'roles' => 'required|in:therapist,receptionist,manager,admin',
            'status' => 'required|in:active,pending,inactive',
        ]);

        try {
            // Check if user already exists
            $user = User::where('email', $validated['email'])->first();

            if (!$user) {
                // Create user if doesn't exist
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => bcrypt(Str::random(10)),
                    'password_reset_required' => true,
                    'temp_password' => Str::random(8),
                ]);
            } else {
                // Update existing user's name
                $user->update(['name' => $validated['name']]);

                // Check if staff record already exists for this user
                $existingStaff = Staff::where('user_id', $user->id)->first();

                if ($existingStaff) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'This staff member already exists! Please edit the existing record instead.');
                }
            }

            // Create staff record
            $staff = Staff::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'employment_status' => $validated['status'],
                'roles' => $validated['roles'],
                'branch_id' => $validated['branch_id'],
                'user_id' => $user->id,
            ]);

            return redirect()->route('staff.index')
                ->with('success', 'Staff member added successfully!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error adding staff: ' . $e->getMessage());
        }
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
