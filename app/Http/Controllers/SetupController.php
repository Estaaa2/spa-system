<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\OperatingHours;
use App\Models\Spa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = Auth::user();

        if (!$user->is_owner) {
            return redirect()->route('dashboard');
        }

        if ($user->spa_id) {
            return redirect()->route('setup.branches');
        }

        return view('setup.index');
    }

    public function storeSpa(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->is_owner) {
            return redirect()->route('dashboard');
        }

        if ($user->spa_id) {
            return redirect()->route('setup.branches');
        }

        $validated = $request->validate([
            'spa_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('spas', 'name'),
            ],
        ], [
            'spa_name.required' => 'The spa business name is required.',
            'spa_name.unique' => 'This spa business name is already registered.',
        ]);

        $spa = Spa::create([
            'owner_id' => $user->id,
            'name' => $validated['spa_name'],
        ]);

        $user->update([
            'spa_id' => $spa->id,
        ]);

        return redirect()->route('setup.branches');
    }

    public function branches(): View|RedirectResponse
    {
        $user = Auth::user();

        if (!$user->spa_id) {
            return redirect()->route('setup.index');
        }

        $branches = $user->spa
            ->branches()
            ->with('profile')
            ->get();

        return view('setup.branches', compact('branches'));
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->spa_id) {
            return redirect()->route('setup.index');
        }

        $spa = $user->spa;

        if ($spa->branches()->exists()) {
            return redirect()
                ->route('setup.branches')
                ->with(
                    'error',
                    'Only one branch can be added during initial setup.'
                );
        }

        $validated = $request->validate([
            'branch_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'name')
                    ->where(fn ($query) => $query->where('spa_id', $spa->id)),
            ],
            'location' => [
                'required',
                'string',
                'max:255',
            ],
            'city' => [
                'required',
                'string',
                'max:100',
            ],
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],
            'location_confirmed' => [
                'required',
                'accepted',
            ],
        ], [
            'branch_name.required' => 'The branch name is required.',
            'branch_name.unique' => 'This branch name is already used by your spa.',
            'location.required' => 'Select a valid branch address from the suggestions or map.',
            'city.required' => 'The city or municipality could not be detected.',
            'latitude.required' => 'Select or pin the exact branch location.',
            'longitude.required' => 'Select or pin the exact branch location.',
            'location_confirmed.accepted' => 'Select a valid address suggestion or pin the branch location on the map.',
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];

        if (!$this->isWithinCaviteBounds($latitude, $longitude)) {
            return back()
                ->withErrors([
                    'location' => 'The branch location must be within Cavite.',
                ])
                ->withInput();
        }

        $branch = DB::transaction(function () use (
            $spa,
            $validated,
            $latitude,
            $longitude
        ) {
            $branch = $spa->branches()->create([
                'name' => $validated['branch_name'],
                'location' => $validated['location'],
                'is_main' => true,
            ]);

            $branch->profile()->create([
                'address' => $validated['location'],
                'city' => $validated['city'],
                'latitude' => $latitude,
                'longitude' => $longitude,
                'is_listed' => false,
            ]);

            $days = [
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday',
                'Saturday',
                'Sunday',
            ];

            foreach ($days as $day) {
                $branch->operatingHours()->create([
                    'day_of_week' => $day,
                    'opening_time' => '09:00',
                    'closing_time' => '18:00',
                    'is_closed' => false,
                ]);
            }

            return $branch;
        });

        return redirect()->route(
            'setup.operating-hours',
            $branch
        );
    }

    public function operatingHours(Branch $branch): View|RedirectResponse
    {
        $user = Auth::user();

        if ((int) $user->spa_id !== (int) $branch->spa_id) {
            abort(403);
        }

        $operatingHours = $branch
            ->operatingHours()
            ->orderByRaw(
                "FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')"
            )
            ->get();

        return view(
            'setup.operating-hours',
            compact('branch', 'operatingHours')
        );
    }

    public function updateOperatingHours(
        Request $request,
        Branch $branch
    ): RedirectResponse {
        $user = Auth::user();

        if ((int) $user->spa_id !== (int) $branch->spa_id) {
            abort(403);
        }

        $hoursData = $request->input('hours', []);

        if (empty($hoursData)) {
            return back()->withErrors([
                'hours' => 'Operating hours are required.',
            ]);
        }

        $openDays = 0;

        foreach ($hoursData as $index => $hour) {
            $isClosed = isset($hour['is_closed']) &&
                $hour['is_closed'] == '1';

            if ($isClosed) {
                continue;
            }

            $openDays++;

            if (
                empty($hour['opening_time']) ||
                empty($hour['closing_time'])
            ) {
                return back()
                    ->withErrors([
                        "hours.$index.opening_time" =>
                            'Opening and closing times are required unless the day is marked as closed.',
                    ])
                    ->withInput();
            }

            if (
                $hour['closing_time'] <=
                $hour['opening_time']
            ) {
                return back()
                    ->withErrors([
                        "hours.$index.closing_time" =>
                            'Closing time must be after opening time.',
                    ])
                    ->withInput();
            }
        }

        if ($openDays === 0) {
            return back()
                ->withErrors([
                    'hours' => 'At least one day must remain open.',
                ])
                ->withInput();
        }

        DB::transaction(function () use ($hoursData, $branch) {
            foreach ($hoursData as $hourData) {
                $isClosed = isset($hourData['is_closed']) &&
                    $hourData['is_closed'] == '1';

                OperatingHours::where('id', $hourData['id'])
                    ->where('branch_id', $branch->id)
                    ->update([
                        'opening_time' =>
                            $hourData['opening_time'] ?? '09:00',
                        'closing_time' =>
                            $hourData['closing_time'] ?? '18:00',
                        'is_closed' => $isClosed,
                    ]);
            }
        });

        return redirect()
            ->route('setup.documents')
            ->with(
                'success',
                'Operating hours saved successfully.'
            );
    }

    public function documents(): View|RedirectResponse
    {
        $user = Auth::user();

        if (!$user->spa_id) {
            return redirect()->route('setup.index');
        }

        $spa = $user
            ->spa()
            ->with('verificationDocuments')
            ->firstOrFail();

        if (!$spa->branches()->exists()) {
            return redirect()->route('setup.branches');
        }

        return view(
            'setup.documents',
            compact('spa')
        );
    }

    public function complete(): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->spa_id) {
            return redirect()->route('setup.index');
        }

        $spa = $user
            ->spa()
            ->with('verificationDocuments')
            ->firstOrFail();

        if (!$spa->branches()->exists()) {
            return redirect()->route('setup.branches');
        }

        $requiredDocuments = [
            'government_id',
            'dti_sec',
            'bir_certificate',
            'business_permit',
        ];

        $uploadedDocuments = $spa
            ->verificationDocuments
            ->pluck('document_type')
            ->unique()
            ->toArray();

        $hasAllDocuments =
            count(
                array_intersect(
                    $requiredDocuments,
                    $uploadedDocuments
                )
            ) === count($requiredDocuments);

        if (!$hasAllDocuments) {
            return redirect()
                ->route('setup.documents')
                ->with(
                    'error',
                    'Upload all required verification documents before completing setup.'
                );
        }

        return redirect()
            ->route('dashboard')
            ->with(
                'success',
                'Business setup completed. Your verification documents are now ready for administrator review.'
            );
    }

    private function isWithinCaviteBounds(
        float $latitude,
        float $longitude
    ): bool {
        return $latitude >= 14.020 &&
            $latitude <= 14.520 &&
            $longitude >= 120.620 &&
            $longitude <= 121.100;
    }
}
