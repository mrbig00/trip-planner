<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Queues Google Analytics (GA4) events to be fired on the *next* full page
 * load — for code that has no active Livewire request to dispatch a browser
 * event over, such as an auth event listener or a plain controller.
 *
 * From inside a Livewire/Volt component, prefer
 * App\Livewire\Concerns\TracksAnalyticsEvents::trackEvent() instead: it
 * fires immediately as part of the component's own response, rather than
 * waiting for the next page load.
 */
class Analytics
{
    private const SESSION_KEY = 'analytics.queued_events';

    /**
     * Queue an event for the browser to fire once the next page finishes loading.
     */
    public static function queue(string $event, array $params = []): void
    {
        if (blank(config('services.google_analytics.id'))) {
            return;
        }

        session()->push(self::SESSION_KEY, [
            'event' => $event,
            'params' => $params,
        ]);
    }

    /**
     * Get and clear every queued event, so each one fires exactly once.
     */
    public static function pullQueued(): array
    {
        return session()->pull(self::SESSION_KEY, []);
    }
}
