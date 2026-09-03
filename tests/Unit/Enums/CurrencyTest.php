<?php

use App\Enums\Currency;

test('every case has a symbol, so the match arm was not forgotten', function () {
    foreach (Currency::cases() as $currency) {
        expect($currency->symbol())->toBeString()->not->toBeEmpty();
    }
});

test('default is USD', function () {
    expect(Currency::default())->toBe(Currency::USD);
});

test('RON is a supported currency with the "lei" symbol', function () {
    expect(Currency::RON->symbol())->toBe('lei')
        ->and(Currency::RON->label())->toBe('RON (lei)');
});
