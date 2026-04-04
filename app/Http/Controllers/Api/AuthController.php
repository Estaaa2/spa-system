<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role; // Add this import

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'first_name'  => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name'   => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'first_name'  => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name'   => $request->last_name,
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
        ]);

        // ✅ UNCOMMENT THIS - Assign customer role
        $role = Role::where('name', 'customer')
            ->where('guard_name', 'web')
            ->first();

        if ($role) {
            $user->assignRole($role);
        } else {
            // If role doesn't exist, create it
            $role = Role::create(['name' => 'customer', 'guard_name' => 'web']);
            $user->assignRole($role);
        }

        // Skip email verification for mobile
        $user->markEmailAsVerified();

        $token = $user->createToken('mobile_app')->plainTextToken;

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        // Ensure user has a role (for existing users without roles)
        if ($user->getRoleNames()->isEmpty()) {
            $role = Role::where('name', 'customer')->first();
            if ($role) {
                $user->assignRole($role);
            }
        }

        $user->tokens()->delete();

        $token = $user->createToken('mobile_app')->plainTextToken;

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        return response()->json($this->formatUser($request->user()));
    }

    private function formatUser(User $user): array
    {
        // Get the actual role from Spatie, fallback to 'customer'
        $role = $user->getRoleNames()->first() ?? 'customer';

        return [
            'id'          => $user->id,
            'first_name'  => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name'   => $user->last_name,
            'full_name'   => $user->name,
            'email'       => $user->email,
            'role'        => $role, // Use actual role from database
            'spa_id'      => $user->spa_id,
            'branch_id'   => $user->branch_id,
            'is_owner'    => $user->is_owner,
        ];
    }
}
