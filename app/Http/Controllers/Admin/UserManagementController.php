<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');

        $users = User::query()

            // Exclude users with 'admin' role
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'admin');
            })
            ->when($q, function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    // Update search to use first_name and last_name instead of 'name'
                    $q2->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$q}%");
                });
            })

            ->with('roles')
            ->orderBy('last_name')  // Changed from 'name' to 'last_name'
            ->orderBy('first_name') // Then order by first_name
            ->paginate(15)
            ->withQueryString();

        $roles = Role::orderBy('name')->get();

        return view('admin.users.index', compact('users', 'roles', 'q'));
    }

    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user->syncRoles([$validated['role']]);

        return back()->with('success', "Role updated for {$user->email}.");
    }

    public function destroy(User $user)
    {
        if ($user->hasRole('admin')) {
            return back()->with('error', 'Admin users cannot be deleted.');
        }

        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $userEmail = $user->email;

        $user->delete();

        return back()->with('success', "User {$userEmail} deleted successfully.");
    }

    public function restore($id)
    {
        $user = User::onlyTrashed()->findOrFail($id);

        $user->restore();

        return back()->with('success', "User {$user->email} restored successfully.");
    }
}
