<?php

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('registering queues sign_up and login analytics events when analytics is configured', function () {
    config(['services.google_analytics.id' => 'G-TEST123']);

    $this->post(route('register.store'), [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(App\Support\Analytics::pullQueued())->toBe([
        ['event' => 'sign_up', 'params' => ['method' => 'password']],
        ['event' => 'login', 'params' => ['method' => 'password']],
    ]);
});

test('registering queues no analytics event when analytics is not configured', function () {
    config(['services.google_analytics.id' => null]);

    $this->post(route('register.store'), [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(App\Support\Analytics::pullQueued())->toBe([]);
});
