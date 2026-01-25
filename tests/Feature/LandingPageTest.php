<?php

use App\Models\User;

test('landing page returns successful response', function () {
    $response = $this->get(route('home'));

    $response->assertStatus(200);
});

test('landing page shows app name in navbar', function () {
    $response = $this->get(route('home'));

    $response->assertStatus(200);
    $response->assertSee(config('app.name'), false);
});

test('guests see login and get started links on landing page', function () {
    $response = $this->get(route('home'));

    $response->assertStatus(200);
    $response->assertSee(route('login'), false);
    $response->assertSee(route('register'), false);
});

test('authenticated users see dashboard link on landing page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertStatus(200);
    $response->assertSee(route('dashboard'), false);
});

test('landing page has hero, features, cta and footer sections', function () {
    $response = $this->get(route('home'));

    $response->assertStatus(200);
    $response->assertSee('Plan your trips in one place', false);
    $response->assertSee('Plan trips', false);
    $response->assertSee('Track expenses', false);
    $response->assertSee('Invite others', false);
    $response->assertSee('Start planning your next trip', false);
});
