<?php

use App\Models\User;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
});

test('two factor settings page can be rendered', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.show'))
        ->assertOk()
        ->assertSee('Two Factor Authentication')
        ->assertSee('Disabled');
});

test('two factor settings page requires password confirmation when enabled', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('two-factor.show'));

    $response->assertRedirect(route('password.confirm'));
});

test('two factor settings page returns forbidden response when two factor is disabled', function () {
    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.show'));

    $response->assertForbidden();
});

test('two factor authentication disabled when confirmation abandoned between requests', function () {
    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->actingAs($user);

    $component = Volt::test('settings.two-factor');

    $component->assertSet('twoFactorEnabled', false);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
    ]);
});

test('enable opens the modal and requires confirmation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('settings.two-factor')
        ->call('enable')
        ->assertSet('showModal', true)
        ->assertSet('twoFactorEnabled', false);

    expect($user->fresh()->two_factor_secret)->not->toBeNull();
});

test('enable immediately marks two factor as enabled when confirmation is not required', function () {
    Features::twoFactorAuthentication(['confirm' => false]);

    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('settings.two-factor')
        ->call('enable')
        ->assertSet('twoFactorEnabled', true);
});

test('loadSetupData adds an error when the secret cannot be decrypted', function () {
    $user = User::factory()->create([
        'two_factor_secret' => 'not-a-valid-encrypted-payload',
        'two_factor_confirmed_at' => now(),
    ]);
    $this->actingAs($user);

    Volt::test('settings.two-factor')
        ->call('enable')
        ->assertHasErrors(['setupData'])
        ->assertSet('qrCodeSvg', '')
        ->assertSet('manualSetupKey', '');
});

test('showVerificationIfNecessary shows the verification step when confirmation is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('settings.two-factor')
        ->call('enable')
        ->call('showVerificationIfNecessary')
        ->assertSet('showVerificationStep', true);
});

test('showVerificationIfNecessary closes the modal when confirmation is not required', function () {
    Features::twoFactorAuthentication(['confirm' => false]);

    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('settings.two-factor')
        ->call('enable')
        ->call('showVerificationIfNecessary')
        ->assertSet('showModal', false);
});

test('confirmTwoFactor succeeds with a valid code', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Volt::test('settings.two-factor')->call('enable');

    $secret = decrypt($user->fresh()->two_factor_secret);
    $validCode = app(Google2FA::class)->getCurrentOtp($secret);

    $component->set('code', $validCode)
        ->call('confirmTwoFactor')
        ->assertSet('twoFactorEnabled', true)
        ->assertSet('showModal', false);

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('confirmTwoFactor fails with an invalid code', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Volt::test('settings.two-factor')->call('enable');

    $component->set('code', '000000')
        ->call('confirmTwoFactor')
        ->assertHasErrors(['code']);

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('resetVerification clears the code and verification step', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('settings.two-factor')
        ->call('enable')
        ->call('showVerificationIfNecessary')
        ->set('code', '123456')
        ->call('resetVerification')
        ->assertSet('code', '')
        ->assertSet('showVerificationStep', false);
});

test('disable turns off two factor authentication', function () {
    $user = User::factory()->withTwoFactor()->create();
    $this->actingAs($user);

    Volt::test('settings.two-factor')
        ->call('disable')
        ->assertSet('twoFactorEnabled', false);

    expect($user->fresh()->two_factor_secret)->toBeNull();
});

test('closeModal resets modal state', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('settings.two-factor')
        ->call('enable')
        ->set('code', '123456')
        ->call('closeModal')
        ->assertSet('showModal', false)
        ->assertSet('showVerificationStep', false)
        ->assertSet('code', '')
        ->assertSet('manualSetupKey', '')
        ->assertSet('qrCodeSvg', '');
});

test('modalConfig reflects the enabled state', function () {
    $user = User::factory()->withTwoFactor()->create();
    $this->actingAs($user);

    Volt::test('settings.two-factor')
        ->assertSet('modalConfig', fn ($config) => $config['title'] === __('Two-Factor Authentication Enabled'));
});

test('modalConfig reflects the verification step', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('settings.two-factor')
        ->call('enable')
        ->call('showVerificationIfNecessary')
        ->assertSet('modalConfig', fn ($config) => $config['title'] === __('Verify Authentication Code'));
});

test('modalConfig reflects the initial setup step', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('settings.two-factor')
        ->call('enable')
        ->assertSet('modalConfig', fn ($config) => $config['title'] === __('Enable Two-Factor Authentication'));
});
