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
use App\Mail\StaffCredentialsMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\RedirectResponse;

class SetupController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = Auth::user();

        if (!$user->is_owner) {
            return redirect()->route('dashboard');
        }

        // If spa is fully set up, go to dashboard
        if ($user->spa_id && $user->spa->is_setup_complete) {
            return redirect()->route('dashboard');
        }

        // Otherwise, always show setup index (whether spa exists or not)
        return view('setup.index');
    }

    public function storeSpa(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->is_owner) {
            return redirect()->route('dashboard');
        }

        // If spa already exists, just go to branches
        if ($user->spa_id) {
            return redirect()->route('setup.branches');
        }

        $validated = $request->validate([
            'spa_name' => ['required', 'string', 'max:255'],
        ]);

        $spa = Spa::create([
            'owner_id' => $user->id,
            'name' => $validated['spa_name'],
        ]);

        $user->update(['spa_id' => $spa->id]);

        return redirect()->route('setup.branches');
    }

    public function branches(): View|RedirectResponse
    {
        $user = Auth::user();

        if (!$user->spa_id) {
            return redirect()->route('setup.index');
        }

        $branches = $user->spa->branches;

        return view('setup.branches', compact('branches'));
    }

    public function storeBranch(Request $request)
    {
        $request->validate([
            'branch_name' => 'required|string|max:255',
            'location'    => 'required|string|max:255',
            'address'     => 'required|string|max:500',
            'phone'       => 'required|string|max:20',
            'description' => 'nullable|string|max:1000',
        ]);

        $spa = auth()->user()->spa;

        $branch = $spa->branches()->create([
            'name'             => $request->branch_name,
            'location'         => $request->location,
            'has_home_service' => $request->boolean('has_home_service'),
            'is_main'          => $spa->branches()->count() === 0,
        ]);

        $branch->profile()->create([
            'address'     => $request->address,
            'phone'       => $request->phone,
            'description' => $request->description ?? '',
            'is_listed'   => 0,
        ]);

        $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
        foreach ($days as $day) {
            $branch->operatingHours()->create([
                'day_of_week'  => $day,
                'opening_time' => '09:00',
                'closing_time' => '18:00',
                'is_closed'    => false,
            ]);
        }

        return redirect()->route('setup.branches')
            ->with('success', 'Branch added successfully!');
    }

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

    public function updateOperatingHours(Request $request, Branch $branch): RedirectResponse
    {
        $user = Auth::user();

        if ($user->spa_id !== $branch->spa_id) {
            abort(403);
        }

        $hoursData = $request->input('hours', []);

        foreach ($hoursData as $index => $hour) {
            $isClosed = isset($hour['is_closed']) && $hour['is_closed'] == '1';

            if (!$isClosed) {
                if (empty($hour['opening_time']) || empty($hour['closing_time'])) {
                    return back()->withErrors(["hours.$index.opening_time" => "Opening and closing times are required unless the day is marked as closed."]);
                }
            }
        }

        foreach ($hoursData as $index => $hourData) {
            $isClosed = isset($hourData['is_closed']) && $hourData['is_closed'] == '1';

            OperatingHours::where('id', $hourData['id'])
                ->where('branch_id', $branch->id)
                ->update([
                    'opening_time' => $hourData['opening_time'] ?? '09:00',
                    'closing_time' => $hourData['closing_time'] ?? '18:00',
                    'is_closed'    => $isClosed ? true : false,
                ]);
        }

        return redirect()->route('setup.branches')->with('success', 'Operating hours updated');
    }

    public function staff(Branch $branch): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user->spa_id !== $branch->spa_id) {
            abort(403);
        }

        $branchUsers = $branch->users()->with('roles')->get();

        return view('setup.staff', compact('branch', 'branchUsers'));
    }

    public function storeStaff(Request $request, Branch $branch): RedirectResponse
    {
        $user = Auth::user();

        if ($user->spa_id !== $branch->spa_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'role'  => ['required', 'in:manager,receptionist,therapist'],
        ]);

        DB::transaction(function () use ($validated, $user, $branch) {
            $tempPassword = Str::random(12);

            $newUser = User::create([
                'name'                     => $validated['name'],
                'email'                    => $validated['email'],
                'password'                 => Hash::make($tempPassword),
                'spa_id'                   => $user->spa_id,
                'branch_id'                => $branch->id,
                'temp_password'            => $tempPassword,
                'password_reset_required'  => true,
            ]);

            $newUser->assignRole($validated['role']);
            $newUser->markEmailAsVerified();

            Staff::create([
                'user_id'           => $newUser->id,
                'spa_id'            => $user->spa_id,
                'branch_id'         => $branch->id,
                'employment_status' => 'active',
                'hire_date'         => now(),
            ]);

            $this->sendStaffCredentials($newUser, $tempPassword);
        });

        return redirect()->route('setup.staff', $branch)
            ->with('success', 'Staff member created and credentials sent');
    }

    private function sendStaffCredentials(User $user, string $tempPassword): void
    {
        Mail::to($user->email)->send(new StaffCredentialsMail($user, $tempPassword));
    }

    public function complete()
    {
        $spa = auth()->user()->spa;
        $spa->update(['is_setup_complete' => true]);

        return redirect()->route('dashboard');
    }
}
