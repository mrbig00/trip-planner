<?php

use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('invoking Logout logs the user out and redirects home', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(Auth::guard('web')->check())->toBeTrue();

    $response = (new Logout)();

    expect(Auth::guard('web')->check())->toBeFalse();
    expect($response->getTargetUrl())->toBe(url('/'));
});
