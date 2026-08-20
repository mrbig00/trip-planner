{{--
    Google Analytics (GA4).

    This partial only bridges server config to the client — every actual
    decision (whether the visitor has consented, whether to load gtag.js,
    when to fire page views) lives in resources/js/analytics.js. Nothing
    here loads a script or sets a cookie.
--}}
@if ($gaMeasurementId = config('services.google_analytics.id'))
    <meta name="ga-measurement-id" content="{{ $gaMeasurementId }}">
    <meta name="privacy-policy-url" content="{{ route('privacy') }}">

    {{--
        Flush any events queued via App\Support\Analytics::queue() — analytics
        events raised from outside a Livewire request/response cycle (e.g.
        the Login/Registered listeners in AppServiceProvider), which have no
        component to dispatch a browser event from and so are handed to the
        very next page to fire instead. trackEvent() itself still won't send
        anything on to Google until the visitor has consented.

        Nested under the same check as the measurement ID: Analytics::queue()
        already refuses to queue anything while analytics is disabled, and
        this keeps the flush from ever running against events that could only
        exist from before analytics was disabled.
    --}}
    @php($queuedAnalyticsEvents = \App\Support\Analytics::pullQueued())
    @if ($queuedAnalyticsEvents)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                @foreach ($queuedAnalyticsEvents as $queuedEvent)
                    window.trackEvent?.(@js($queuedEvent['event']), @js($queuedEvent['params']));
                @endforeach
            });
        </script>
    @endif
@endif
