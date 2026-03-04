<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchProfileController extends Controller
{
    public function edit(Branch $branch)
    {
        $this->authorize('update', $branch); // RBAC: only owner/manager

        // Ensure profile exists
        $profile = $branch->profile ?? $branch->profile()->create([]);

        return view('staff.branches.profile_edit', compact('branch', 'profile'));
    }

    public function update(Request $request, Branch $branch)
    {
        $this->authorize('update', $branch);

        $profile = $branch->profile ?? $branch->profile()->create([]);

        $data = $request->validate([
            'is_listed' => 'nullable|boolean',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|max:2048',
            'gallery_images.*' => 'nullable|image|max:2048',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'amenities' => 'nullable|array',
            'amenities.*' => 'nullable|string|max:100',
        ]);

        // Handle cover image upload
        if($request->hasFile('cover_image')){
            $data['cover_image'] = $request->file('cover_image')->store('branch_profiles', 'public');
        }

        // Handle gallery images upload
        if($request->hasFile('gallery_images')){
            $data['gallery_images'] = [];
            foreach($request->file('gallery_images') as $img){
                $data['gallery_images'][] = $img->store('branch_profiles', 'public');
            }
        }

        // Default amenities to empty array if null
        if(!isset($data['amenities'])){
            $data['amenities'] = $profile->amenities ?? [];
        }

        $profile->update($data);

        return back()->with('success', 'Branch profile updated.');
    }
}