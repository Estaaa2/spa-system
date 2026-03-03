<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Spa;
use Illuminate\Http\Request;

class RegisteredSpaController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->q;

        $spas = Spa::with('owner')
            ->when($q, function($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhereHas('owner', function($q2) use ($q) {
                        $q2->where('name', 'like', "%{$q}%");
                    });
            })
            ->paginate(10); // ← paginate instead of all()

        return view('admin.registered-spas.index', compact('spas', 'q'));
    }

    public function edit(Spa $spa)
    {
        return view('admin.registered-spas.edit', compact('spa'));
    }

    public function update(Request $request, Spa $spa)
    {
        $request->validate([
            'business_tier' => 'required|in:basic,professional,enterprise',
        ]);

        $spa->update([
            'business_tier' => $request->business_tier,
        ]);

        return redirect()->route('admin.registered-spas.index')->with('success', 'Spa updated successfully.');
    }
}