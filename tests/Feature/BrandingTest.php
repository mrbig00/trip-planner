<?php

use Illuminate\Support\Facades\Blade;

test('the app logo icon renders the rebranded mark', function () {
    $html = Blade::render('<x-app-logo-icon />');

    expect($html)
        ->toContain('rx="18"')
        ->toContain('fill="#141414"');
});

test('the login page links the favicons and manifest', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee('/favicon-96x96.png', false);
    $response->assertSee('/favicon.svg', false);
    $response->assertSee('/favicon.ico', false);
    $response->assertSee('/apple-touch-icon.png', false);
    $response->assertSee('/site.webmanifest', false);
});

test('the web manifest advertises the app name and icon', function () {
    $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true);

    expect($manifest['name'])->toBe('Trip Planner')
        ->and($manifest['short_name'])->toBe('Trip Planner')
        ->and($manifest['icons'][0]['src'])->toBe('/favicon-96x96.png');
});

test('the participant avatar applies the color slot ring class', function () {
    $html = Blade::render('<x-participant-avatar name="Ada Lovelace" initials="AL" :color-slot="3" />');

    expect($html)->toContain('ring-[var(--color-participant-3)]');
});

test('the participant avatar never sends the raw name to the avatar service', function () {
    $html = Blade::render('<x-participant-avatar name="Ada Lovelace" initials="AL" />');

    expect($html)
        ->toContain('seed='.hash('sha256', 'Ada Lovelace'))
        ->not->toContain('Ada+Lovelace')
        ->not->toContain(urlencode('Ada Lovelace'));
});
