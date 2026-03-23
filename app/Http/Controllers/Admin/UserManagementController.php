<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

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
                    $q2->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })

            ->with('roles')
            ->orderBy('name')
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

        // Prevent removing admin from itself if you want (optional)
        // if ($user->id === auth()->id() && $validated['role'] !== 'admin') { ... }

        $user->syncRoles([$validated['role']]);

        return back()->with('success', "Role updated for {$user->email}.");
    }

    public function destroy(User $user)
    {
        // Extra protection: do not allow deleting admin users
        if ($user->hasRole('admin')) {
            return back()->with('error', 'Admin users cannot be deleted.');
        }

        // Optional: do not allow deleting your own account
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

