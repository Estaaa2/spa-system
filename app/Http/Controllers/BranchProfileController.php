<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchProfileController extends Controller
{
    public function edit(Branch $branch)
    {
        $this->authorize('update', $branch); // RBAC: only owner/manager

        // Ensure profile exists
        $profile = $branch->profile ?? $branch->profile()->create([
            'branch_id' => $branch->id,
        ]);

        return view('staff.branches.profile_edit', compact('branch', 'profile'));
    }

    public function update(Request $request, Branch $branch)
    {
        $this->authorize('update', $branch);

        $profile = $branch->profile ?? $branch->profile()->create([
            'branch_id' => $branch->id,
        ]);

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

        DB::transaction(function() use ($request, $profile, $data) {

            // Boolean cast
            $data['is_listed'] = $request->boolean('is_listed');

            // Cover image
            if ($request->hasFile('cover_image')) {
                $data['cover_image'] = $request->file('cover_image')->store('branch_profiles', 'public');
            } else {
                $data['cover_image'] = $profile->cover_image;
            }

            // Gallery images
            if ($request->hasFile('gallery_images')) {
                $existingGallery = $profile->gallery_images ?? [];
                foreach ($request->file('gallery_images') as $img) {
                    $existingGallery[] = $img->store('branch_profiles', 'public');
                }
                $data['gallery_images'] = $existingGallery;
            } else {
                $data['gallery_images'] = $profile->gallery_images ?? [];
            }

            // Amenities
            $data['amenities'] = $data['amenities'] ?? $profile->amenities ?? [];

            // Handle nullable fields properly
            $data['address'] = $data['address'] ?? $profile->address;
            $data['latitude'] = $data['latitude'] ?? $profile->latitude;
            $data['longitude'] = $data['longitude'] ?? $profile->longitude;

            // Finally update
            $profile->update($data);
        });

        return back()->with('success', 'Branch profile updated.');
    }
}