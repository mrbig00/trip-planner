<?php

use App\Models\User;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

beforeEach(function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
});

test('mount populates no codes when two-factor is disabled', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('settings.two-factor.recovery-codes')
        ->assertSet('recoveryCodes', []);
});

test('mount decrypts and populates codes when two-factor is enabled', function () {
    $user = User::factory()->withTwoFactor()->create();
    $this->actingAs($user);

    Volt::test('settings.two-factor.recovery-codes')
        ->assertSet('recoveryCodes', ['recovery-code-1']);
});

test('mount adds an error and stays empty when codes are corrupted', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => 'not-a-valid-encrypted-payload',
    ]);
    $this->actingAs($user);

    Volt::test('settings.two-factor.recovery-codes')
        ->assertHasErrors(['recoveryCodes'])
        ->assertSet('recoveryCodes', []);
});

test('regenerateRecoveryCodes produces a new set of codes', function () {
    $user = User::factory()->withTwoFactor()->create();
    $this->actingAs($user);

    Volt::test('settings.two-factor.recovery-codes')
        ->call('regenerateRecoveryCodes')
        ->assertSet('recoveryCodes', fn ($codes) => $codes !== ['recovery-code-1'] && count($codes) > 0);
});
