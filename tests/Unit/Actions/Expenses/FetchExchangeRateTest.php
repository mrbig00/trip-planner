<?php

use App\Enums\Currency;
use Illuminate\Support\Facades\Http;
use App\Actions\Expenses\FetchExchangeRate;

test('it returns the rate reported by the API', function () {
    Http::fake([
        'api.frankfurter.dev/*' => Http::response(['amount' => 1, 'base' => 'EUR', 'date' => '2026-08-28', 'rates' => ['USD' => 1.0842]]),
    ]);

    $rate = app(FetchExchangeRate::class)->fetch(Currency::EUR, Currency::USD);

    expect($rate)->toBe('1.0842');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'base=EUR') && str_contains((string) $request->url(), 'symbols=USD'));
});

test('it fetches the rate for RON like any other currency', function () {
    Http::fake([
        'api.frankfurter.dev/*' => Http::response(['amount' => 1, 'base' => 'EUR', 'date' => '2026-08-28', 'rates' => ['RON' => 5.2558]]),
    ]);

    $rate = app(FetchExchangeRate::class)->fetch(Currency::EUR, Currency::RON);

    expect($rate)->toBe('5.2558');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'base=EUR') && str_contains((string) $request->url(), 'symbols=RON'));
});

test('it returns null and never throws when the same currency is requested on both sides', function () {
    Http::fake();

    $rate = app(FetchExchangeRate::class)->fetch(Currency::USD, Currency::USD);

    expect($rate)->toBeNull();
    Http::assertNothingSent();
});

test('it returns null when the API responds with an error', function () {
    Http::fake([
        'api.frankfurter.dev/*' => Http::response(null, 500),
    ]);

    $rate = app(FetchExchangeRate::class)->fetch(Currency::EUR, Currency::AED);

    expect($rate)->toBeNull();
});

test('it returns null when the pair is missing from the response', function () {
    Http::fake([
        'api.frankfurter.dev/*' => Http::response(['amount' => 1, 'base' => 'EUR', 'date' => '2026-08-28', 'rates' => []]),
    ]);

    $rate = app(FetchExchangeRate::class)->fetch(Currency::EUR, Currency::AED);

    expect($rate)->toBeNull();
});

test('it returns null instead of throwing when the request itself fails', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Could not connect');
    });

    $rate = app(FetchExchangeRate::class)->fetch(Currency::EUR, Currency::USD);

    expect($rate)->toBeNull();
});

test('it caches the result so a second lookup does not hit the API again', function () {
    Http::fake([
        'api.frankfurter.dev/*' => Http::response(['amount' => 1, 'base' => 'EUR', 'date' => '2026-08-28', 'rates' => ['USD' => 1.1]]),
    ]);

    $action = app(FetchExchangeRate::class);
    $action->fetch(Currency::EUR, Currency::USD);
    $action->fetch(Currency::EUR, Currency::USD);

    Http::assertSentCount(1);
});
