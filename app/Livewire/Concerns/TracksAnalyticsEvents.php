<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * Lets a Livewire/Volt component fire a Google Analytics (GA4) event in the
 * browser as part of its own response — no page reload required, and it
 * works whether the component stays put or redirects afterward.
 *
 * For analytics events outside of a component's request/response cycle
 * (e.g. login, registration), use App\Support\Analytics::queue() instead.
 */
trait TracksAnalyticsEvents
{
    protected function trackEvent(string $event, array $params = []): void
    {
        // dispatch()'s own first parameter is named $event, so the payload
        // key can't be `event` too — named arguments would collide with it.
        $this->dispatch('analytics-event', name: $event, params: $params);
    }
}
