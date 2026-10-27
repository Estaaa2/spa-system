<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

// Updated for Levictas (Phase 7). The profile page shows account details read-only and
// changes the password through PUT /profile/password. The Breeze "update profile" and
// "delete account" tests were removed: the page has no such forms and DELETE /profile is
// not routed. PATCH /profile is still routed — see the open issue in the Phase 7 notes.

test('profile page is displayed', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile')->assertOk();
});

test('password can be changed from the profile page', function () {
    $user = User::factory()->create(['password_reset_required' => true]);

    $this->actingAs($user)
        ->from('/profile')
        ->put(route('profile.password'), [
            'current_password' => 'password',
            'password' => 'N3w-Passw0rd!',
            'password_confirmation' => 'N3w-Passw0rd!',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect(Hash::check('N3w-Passw0rd!', $user->password))->toBeTrue();
    expect((bool) $user->password_reset_required)->toBeFalse();
});

test('the current password is required to change it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/profile')
        ->put(route('profile.password'), [
            'current_password' => 'wrong-password',
            'password' => 'N3w-Passw0rd!',
            'password_confirmation' => 'N3w-Passw0rd!',
        ])
        ->assertSessionHasErrors('current_password');
});
