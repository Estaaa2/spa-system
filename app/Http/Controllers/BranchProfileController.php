<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchProfileController extends Controller
{
    public function edit(Branch $branch)
    {
        $this->authorize('update', $branch); // RBAC: only owner/manager
        $profile = $branch->profile ?? $branch->profile()->create([]);
        return view('staff.branches.profile_edit', compact('branch', 'profile'));
    }

    public function update(Request $request, Branch $branch)
    {
        $this->authorize('update', $branch);

        $profile = $branch->profile ?? $branch->profile()->create([]);
        $data = $request->validate([
            'is_listed' => 'boolean',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image',
            'gallery_images.*' => 'nullable|image',
            'phone' => 'nullable|string',
            'opening_time' => 'nullable',
            'closing_time' => 'nullable',
        ]);

        // handle file uploads if needed
        if($request->hasFile('cover_image')){
            $data['cover_image'] = $request->file('cover_image')->store('branch_profiles', 'public');
        }

        if($request->hasFile('gallery_images')){
            $data['gallery_images'] = [];
            foreach($request->file('gallery_images') as $img){
                $data['gallery_images'][] = $img->store('branch_profiles', 'public');
            }
        }

        $profile->update($data);

        return back()->with('success', 'Branch profile updated.');
    }
}
