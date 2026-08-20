{{--
    Starting-point privacy policy, not reviewed by counsel. Covers what this
    app actually does today (account data, trip content, GA4 analytics
    gated behind the cookie consent banner — see resources/js/analytics.js).
    Revisit this whenever data collection changes, and have a human — ideally
    a lawyer — review the text before treating it as final.
--}}
@extends('components.layouts.landing')

@section('content')
    {{-- Navbar --}}
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-white" href="{{ url('/') }}">{{ config('app.name') }}</a>
            <button class="navbar-toggler border-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-2 gap-lg-3">
                    @auth
                        <li class="nav-item">
                            <a class="nav-link text-light text-opacity-90" href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link text-light text-opacity-90" href="{{ route('login') }}">Log in</a>
                        </li>
                        @if (Route::has('register'))
                            <li class="nav-item">
                                <a class="btn btn-primary btn-sm" href="{{ route('register') }}">Get started</a>
                            </li>
                        @endif
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <section class="py-5">
        <div class="container" style="max-width: 42rem;">
            <h1 class="h2 fw-bold mb-2">Privacy Policy</h1>
            <p class="text-secondary mb-5">Last updated: August 21, 2026</p>

            <div class="d-flex flex-column gap-4">
                <div>
                    <h2 class="h5 fw-semibold">Who we are</h2>
                    <p class="mb-0">
                        {{ config('app.name') }} is operated by Szanto Zoltan ("we", "us"). For anything in this
                        policy, or to exercise any of the rights below, contact
                        <a href="mailto:privacy@trip-planner.szanto-zoltan.com" class="link-light">privacy@trip-planner.szanto-zoltan.com</a>.
                    </p>
                </div>

                <div>
                    <h2 class="h5 fw-semibold">What we collect</h2>
                    <ul class="mb-0">
                        <li><strong>Account data</strong> — your name and email address, and, if you sign in with Google, the identifier Google provides to link your account.</li>
                        <li><strong>Trip content</strong> — whatever you and the people you invite add: trips, locations, expenses, comments, and settlements.</li>
                        <li><strong>Usage data</strong> — pages visited, approximate location derived from your IP address, device and browser type, and referring site, collected via Google Analytics only if you accept analytics cookies.</li>
                    </ul>
                </div>

                <div>
                    <h2 class="h5 fw-semibold">Cookies</h2>
                    <ul class="mb-0">
                        <li><strong>Session cookie</strong> — required for the app to work (staying logged in). This is set regardless of your analytics choice, since the app can't function without it.</li>
                        <li><strong>Google Analytics cookies</strong> — only set once you accept them via the cookie banner. You can change your mind at any time using the "Cookie settings" link that stays available after your first choice.</li>
                    </ul>
                </div>

                <div>
                    <h2 class="h5 fw-semibold">Why we process it, and on what basis</h2>
                    <ul class="mb-0">
                        <li>Account and trip data: to provide the service you've asked for (performance of a contract).</li>
                        <li>Analytics data: to understand how the app is used and improve it (your consent — withdrawable at any time).</li>
                    </ul>
                </div>

                <div>
                    <h2 class="h5 fw-semibold">Who we share it with</h2>
                    <p class="mb-0">
                        We don't sell your data. If you accept analytics cookies, usage data is processed by
                        Google Analytics (Google Ireland Limited / Google LLC) under
                        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener" class="link-light">Google's privacy policy</a>,
                        which may involve transferring data outside the EU/EEA under Google's standard
                        contractual clauses. If you sign in with Google, your authentication is likewise
                        handled by Google.
                    </p>
                </div>

                <div>
                    <h2 class="h5 fw-semibold">How long we keep it</h2>
                    <p class="mb-0">
                        Account and trip data is kept for as long as your account exists, and deleted (along
                        with the trips you created) if you delete your account. Analytics data is retained
                        according to our Google Analytics configuration, currently 14 months.
                    </p>
                </div>

                <div>
                    <h2 class="h5 fw-semibold">Your rights</h2>
                    <p class="mb-0">
                        Under the GDPR you can ask us to access, correct, delete, or export your data, object
                        to or restrict how we process it, and withdraw analytics consent at any time (via
                        "Cookie settings"). Email us at the address above to exercise any of these, or lodge a
                        complaint with your local data protection authority.
                    </p>
                </div>

                <div>
                    <h2 class="h5 fw-semibold">Changes to this policy</h2>
                    <p class="mb-0">
                        If what we collect or why changes, we'll update this page and the date at the top.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="py-4 bg-dark text-white border-top border-secondary">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <span class="fw-semibold">{{ config('app.name') }}</span>
                <span class="small text-secondary">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</span>
            </div>
        </div>
    </footer>
@endsection
