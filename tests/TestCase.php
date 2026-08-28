<?php

namespace Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Outbound HTTP safety net: some Livewire components (e.g.
     * expenses.create, App\Livewire\Trips\Show's edit modal) call out to the
     * Frankfurter API to prefill an exchange rate — see
     * App\Actions\Expenses\FetchExchangeRate. preventStrayRequests() means
     * any request without a matching Http::fake(...) stub throws instead of
     * hitting the real network — FetchExchangeRate catches that (like any
     * other failure) and simply returns null, so a test that doesn't care
     * about the lookup is unaffected. This is NOT a blanket Http::fake():
     * that would register a catch-all stub that (matching first, since
     * Laravel resolves stubs in registration order) would shadow every
     * test's own more specific Http::fake([...]) call.
     *
     * FetchExchangeRate also caches its result — the whole suite runs in one
     * PHP process, so a cached rate from one test would otherwise leak into
     * any later test hitting the same currency pair. Flushing the cache
     * keeps them independent regardless of run order.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Cache::flush();
    }
}
