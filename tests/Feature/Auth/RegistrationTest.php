<?php

use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

// Updated for Levictas (Phase 7): registration is held as a pending registration until
// the email is verified (RegisteredUserController + PendingRegistrationService); no user
// row exists and nobody is logged in before that.

test('registration screen can be rendered', function () {
    $this->get('/register')->assertStatus(200);
});

test('registration is held until the email is verified', function () {
    Notification::fake();
    Mail::fake();

    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'N3w-Passw0rd!',
        'password_confirmation' => 'N3w-Passw0rd!',
        'terms' => '1',
    ]);

    $response->assertRedirect(route('pending.verification.notice', absolute: false));
    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
    expect(PendingRegistration::where('email', 'test@example.com')->exists())->toBeTrue();
});

test('registration requires accepting the terms', function () {
    $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'N3w-Passw0rd!',
        'password_confirmation' => 'N3w-Passw0rd!',
    ])->assertSessionHasErrors('terms');
});
