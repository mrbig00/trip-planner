<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('guests cannot access the appearance settings page', function () {
    $response = $this->get(route('appearance.edit'));
    $response->assertRedirect(route('login'));
});

test('appearance settings page can be rendered', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertSee('Appearance');

    Volt::test('settings.appearance')->assertOk();
});
