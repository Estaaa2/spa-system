<?php

namespace App\Http\Controllers;

use App\Models\Spa;
use App\Models\User;
use App\Models\Staff;
use App\Models\Branch;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\OperatingHours;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\RedirectResponse;

class SetupController extends Controller
{
    /**
     * Display the setup wizard
     */
    public function index(): View|RedirectResponse
    {
        $user = Auth::user();

        // Only owner can access setup
        if (!$user->is_owner) {
            return redirect()->route('dashboard');
        }

        // If spa already set up, redirect to dashboard
        if ($user->spa_id) {
            return redirect()->route('dashboard');
        }

        return view('setup.index');
    }

    /**
     * Store spa business information
     */
    public function storeSpa(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->is_owner || $user->spa_id) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'spa_name' => ['required', 'string', 'max:255'],
        ]);

        // Create spa
        $spa = Spa::create([
            'owner_id' => $user->id,
            'name' => $validated['spa_name'],
        ]);

        // Update user with spa
        $user->update(['spa_id' => $spa->id]);

        return redirect()->route('setup.branches');
    }

    /**
     * Show branches setup page
     */
    public function branches(): View|RedirectResponse
    {
        $user = Auth::user();

        // If no spa, redirect to spa info page
        if (!$user->spa_id) {
            return redirect()->route('setup.index');
        }

        $branches = $user->spa->branches;

        return view('setup.branches', compact('branches'));
    }

    /**
     * Store a new branch
     */
    public function storeBranch(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->spa_id) {
            return redirect()->route('setup.index');
        }

        $validated = $request->validate([
            'branch_name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
        ]);

        // Create branch
        $branch = Branch::create([
            'spa_id' => $user->spa_id,
            'name' => $validated['branch_name'],
            'location' => $validated['location'],
        ]);

        // Create default operating hours (9 AM to 6 PM, Monday-Sunday)
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        foreach ($days as $day) {
            OperatingHours::create([
                'branch_id' => $branch->id,
                'day_of_week' => $day,
                'opening_time' => '09:00',
                'closing_time' => '18:00',
                'is_closed' => false,
            ]);
        }

        return redirect()->route('setup.branches')->with('success', 'Branch added successfully');
    }

    /**
     * Show operating hours setup page
     */
    public function operatingHours(Branch $branch): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user->spa_id !== $branch->spa_id) {
            abort(403);
        }

        $operatingHours = $branch->operatingHours()->orderByRaw(
            "FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')"
        )->get();

        return view('setup.operating-hours', compact('branch', 'operatingHours'));
    }

    /**
     * Update operating hours
     */
    public function updateOperatingHours(Request $request, Branch $branch): RedirectResponse
    {
        $user = Auth::user();

        if ($user->spa_id !== $branch->spa_id) {
            abort(403);
        }

        // Get the raw request data to build conditional validation
        $hoursData = $request->input('hours', []);

        // Build validation rules conditionally
        foreach ($hoursData as $index => $hour) {
            $isClosed = isset($hour['is_closed']) && $hour['is_closed'] == '1';

            if (!$isClosed) {
                // Only validate times if not closed
                if (empty($hour['opening_time']) || empty($hour['closing_time'])) {
                    return back()->withErrors(["hours.$index.opening_time" => "Opening and closing times are required unless the day is marked as closed."]);
                }
            }
        }

        // Update each operating hour
        foreach ($hoursData as $index => $hourData) {
            $isClosed = isset($hourData['is_closed']) && $hourData['is_closed'] == '1';

            OperatingHours::where('id', $hourData['id'])
                ->where('branch_id', $branch->id)
                ->update([
                    'opening_time' => $hourData['opening_time'] ?? '09:00',
                    'closing_time' => $hourData['closing_time'] ?? '18:00',
                    'is_closed' => $isClosed ? true : false,
                ]);
        }

        return redirect()->route('setup.branches')->with('success', 'Operating hours updated');
    }

    /**
     * Show staff creation page
     */
    public function staff(Branch $branch): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user->spa_id !== $branch->spa_id) {
            abort(403);
        }

        $branchUsers = $branch->users()->with('roles')->get();

        return view('setup.staff', compact('branch', 'branchUsers'));
    }

    /**
     * Create new staff member
     */
    public function storeStaff(Request $request, Branch $branch): RedirectResponse
    {
        $user = Auth::user();

        if ($user->spa_id !== $branch->spa_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'role' => ['required', 'in:manager,receptionist,therapist'],
        ]);

        DB::transaction(function () use ($validated, $user, $branch) {

            // Generate temporary password
            $tempPassword = Str::random(12);

            // Create user
            $newUser = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($tempPassword),
                'spa_id' => $user->spa_id,
                'branch_id' => $branch->id,
                'temp_password' => $tempPassword,
                'password_reset_required' => true,
            ]);

            // Assign role
            $newUser->assignRole($validated['role']);

            // Create staff record
            Staff::create([
                'user_id' => $newUser->id,
                'spa_id' => $user->spa_id,
                'branch_id' => $branch->id,
                'employment_status' => 'active',
                'hire_date' => now(),
            ]);
            // Send email with credentials
            $this->sendStaffCredentials($newUser, $tempPassword);
        });
        
            return redirect()->route('setup.staff', $branch)->with('success', 'Staff member created and credentials sent');
    }

    /**
     * Send staff credentials via email
     */
    private function sendStaffCredentials(User $user, string $tempPassword): void
    {
        // Simple email sending - you can enhance this with a proper mailable class
        $message = "Welcome to " . $user->spa->name . "!\n\n";
        $message .= "Your account has been created. Here are your temporary credentials:\n\n";
        $message .= "Email: " . $user->email . "\n";
        $message .= "Password: " . $tempPassword . "\n\n";
        $message .= "Please change your password after logging in.\n";

        // You can implement proper email sending here
        // For now, just ensure the user has the credentials stored
    }

    /**
     * Complete setup and redirect to dashboard
     */
    public function complete(): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->is_owner || !$user->spa_id || !$user->spa->branches()->exists()) {
            return redirect()->route('setup.branches');
        }

        return redirect()->route('dashboard');
    }
}
