<?php

namespace Tests\Feature\Identity;

use App\Models\IdentityVerification;
use App\Models\User;
use App\Services\Identity\IdentityVerificationService;
use Database\Seeders\IdentityVerificationTestSeeder;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 2 — staff profile page: photo, phone, identity verification form and
 * withdrawal, through real HTTP requests, for every demo state.
 */
class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
    }

    private function account(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function image(string $name = 'p.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 800, 800);
    }

    public function test_page_shows_the_right_identity_state_for_every_demo_account(): void
    {
        $expect = [
            'idv.vera@levictas.test'  => 'Withdraw consent',
            'idv.paolo@levictas.test' => 'Not verified yet',
            'idv.pia@levictas.test'   => 'Waiting for review',
            'idv.rey@levictas.test'   => 'Not approved',
            'idv.carlo@levictas.test' => 'Waiting for review',
            'idv.wendy@levictas.test' => 'Consent withdrawn',
            'idv.rhea@levictas.test'  => 'Performs treatments',
            'idv.nico@levictas.test'  => 'Upload a profile photo',
        ];

        foreach ($expect as $email => $text) {
            $this->actingAs($this->account($email))
                ->get('/profile')
                ->assertOk()
                ->assertSee('Identity Verification')
                ->assertSee($text);
        }

        // The rejection reason is shown to the staff member.
        $this->actingAs($this->account('idv.rey@levictas.test'))
            ->get('/profile')->assertSee('The ID photo is blurry');
    }

    public function test_staff_outside_the_pool_see_no_verification_section(): void
    {
        foreach (['reception.a@levictas.test', 'manager.a@levictas.test', 'owner@levictas.test'] as $email) {
            $this->actingAs($this->account($email))
                ->get('/profile')
                ->assertOk()
                ->assertDontSee('Identity Verification')
                ->assertSee('Profile Photo');
        }

        $this->actingAs($this->account('reception.a@levictas.test'))
            ->post(route('profile.identity.store'), [
                'id_type' => 'philid', 'id_image' => $this->image(), 'selfie' => $this->image(), 'consent' => '1',
            ])
            ->assertForbidden();
    }

    public function test_phone_is_the_only_editable_field(): void
    {
        $user = $this->account('idv.paolo@levictas.test');
        $name = $user->name;

        $this->actingAs($user)
            ->patch(route('profile.update'), ['phone' => '09171234567', 'first_name' => 'Hacker', 'email' => 'x@example.com'])
            ->assertRedirect(route('profile.edit').'#account');

        $user->refresh();
        $this->assertSame('09171234567', $user->phone);
        $this->assertSame($name, $user->name);
        $this->assertSame('idv.paolo@levictas.test', $user->email);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['phone' => '12345'])
            ->assertSessionHasErrorsIn('account', 'phone');
    }

    public function test_photo_upload_replace_and_remove(): void
    {
        $user = $this->account('idv.nico@levictas.test');   // no photo yet

        $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => $this->image()])
            ->assertRedirect(route('profile.edit').'#photo')
            ->assertSessionHas('success', 'Profile photo updated.');
        $this->assertTrue($user->fresh()->hasProfilePhoto());

        $this->actingAs($user)->get('/profile')->assertSee(route('profile-photos.show', ['user' => $user->id], false), false);

        $this->actingAs($user)->delete(route('profile.photo.destroy'))->assertSessionHas('success');
        $this->assertFalse($user->fresh()->hasProfilePhoto());

        $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrorsIn('photo', 'photo');
    }

    public function test_replacing_a_verified_photo_ends_verification_and_says_so(): void
    {
        $vera = $this->account('idv.vera@levictas.test');
        $this->actingAs($vera)->get('/profile')->assertSee('Replace your verified photo?')->assertDontSee('Remove photo');

        $this->actingAs($vera)->post(route('profile.photo.update'), ['photo' => $this->image()])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'verify your identity again'));

        $this->assertFalse($vera->fresh()->isIdentityVerified());
        $this->actingAs($vera)->get('/profile')->assertSee('Verify again');
    }

    public function test_removing_a_photo_is_refused_while_pending(): void
    {
        $pia = $this->account('idv.pia@levictas.test');

        $this->actingAs($pia)->delete(route('profile.photo.destroy'))->assertSessionHasErrorsIn('photo', 'photo');
        $this->assertTrue($pia->fresh()->hasProfilePhoto());
    }

    public function test_submit_needs_consent_and_then_shows_pending(): void
    {
        $paolo = $this->account('idv.paolo@levictas.test');
        $files = ['id_type' => 'philid', 'id_image' => $this->image('id.jpg'), 'selfie' => $this->image('s.jpg')];

        $this->actingAs($paolo)->post(route('profile.identity.store'), $files)
            ->assertSessionHasErrorsIn('identity', 'consent');
        $this->assertSame(0, $paolo->identityVerifications()->count());

        $this->actingAs($paolo)->post(route('profile.identity.store'), $files + ['consent' => '1'])
            ->assertRedirect(route('profile.edit').'#identity')
            ->assertSessionHas('success');

        $row = $paolo->identityVerifications()->first();
        $this->assertSame('pending', $row->status);
        $this->assertSame('web', $row->consent_channel);
        $this->assertSame(config('identity.consent_version'), $row->consent_version);
        $this->actingAs($paolo)->get('/profile')->assertSee('Waiting for review');
    }

    public function test_submit_without_photo_is_refused(): void
    {
        $nico = $this->account('idv.nico@levictas.test');

        $this->actingAs($nico)->post(route('profile.identity.store'), [
            'id_type' => 'philid', 'id_image' => $this->image(), 'selfie' => $this->image(), 'consent' => '1',
        ])->assertSessionHasErrorsIn('identity', 'photo');
    }

    public function test_withdraw_from_pending_and_from_approved(): void
    {
        $pia = $this->account('idv.pia@levictas.test');
        $this->actingAs($pia)->delete(route('profile.identity.withdraw'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'cancelled'));
        $this->assertSame('withdrawn', $pia->latestIdentityVerification()->first()->status);

        $vera = $this->account('idv.vera@levictas.test');
        $this->actingAs($vera)->delete(route('profile.identity.withdraw'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Consent withdrawn'));
        $this->assertFalse($vera->fresh()->isIdentityVerified());

        // Nothing left to withdraw.
        $this->actingAs($vera)->delete(route('profile.identity.withdraw'))->assertSessionHas('error');
    }

    public function test_temporary_password_users_can_still_use_the_photo_and_verification_routes(): void
    {
        $paolo = $this->account('idv.paolo@levictas.test');
        $paolo->forceFill(['password_reset_required' => true])->saveQuietly();

        $this->actingAs($paolo)->get('/dashboard')->assertRedirect(route('profile.edit').'#password');
        $this->actingAs($paolo)->post(route('profile.photo.update'), ['photo' => $this->image()])
            ->assertRedirect(route('profile.edit').'#photo');
    }

    public function test_sidebar_shows_the_photo_or_initials(): void
    {
        $vera = $this->account('idv.vera@levictas.test');
        $this->actingAs($vera)->get('/profile')
            ->assertSee(route('profile-photos.show', ['user' => $vera->id], false), false);

        $nico = $this->account('idv.nico@levictas.test');
        $this->actingAs($nico)->get('/profile')->assertSee($nico->initials());
    }
}
