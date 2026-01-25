@extends('components.layouts.landing')

@section('content')
    {{-- Navbar --}}
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="{{ url('/') }}">{{ config('app.name') }}</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-2 gap-lg-3">
                    @auth
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('dashboard') }}">My trips</a>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">Log in</a>
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

    {{-- Hero --}}
    <section class="py-5 bg-light">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <p class="text-primary fw-semibold small text-uppercase mb-2">Plan together, travel better</p>
                    <h1 class="display-4 fw-bold mb-4">Plan your trips in one place</h1>
                    <p class="lead text-secondary mb-4">
                        Create trips, add locations, track expenses, and invite others—so everyone stays on the same page from idea to destination.
                    </p>
                    @auth
                        <a class="btn btn-primary btn-lg px-4" href="{{ route('dashboard') }}">Go to Dashboard</a>
                    @else
                        <a class="btn btn-primary btn-lg px-4 me-2" href="{{ route('register') }}">Get started</a>
                        <a class="btn btn-outline-secondary btn-lg px-4" href="{{ route('login') }}">Log in</a>
                    @endauth
                </div>
                <div class="col-lg-6 text-center pt-4 pt-lg-0">
                    <div class="rounded-3 bg-white shadow-sm border d-inline-flex align-items-center justify-content-center p-5">
                        <i class="bi bi-geo-alt-fill display-1 text-primary opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="h1 fw-bold mb-3">Everything you need to plan a trip</h2>
                <p class="lead text-secondary">Simple tools that help you and your group stay organized.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="rounded-2 bg-primary bg-opacity-10 text-primary d-inline-flex p-3 mb-3">
                                <i class="bi bi-map fs-3"></i>
                            </div>
                            <h3 class="h5 fw-semibold mb-2">Plan trips</h3>
                            <p class="text-secondary mb-0 small">Create a trip, set dates, and add a description. One place for all the details.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="rounded-2 bg-primary bg-opacity-10 text-primary d-inline-flex p-3 mb-3">
                                <i class="bi bi-pin-map fs-3"></i>
                            </div>
                            <h3 class="h5 fw-semibold mb-2">Add locations</h3>
                            <p class="text-secondary mb-0 small">Save places you want to see. Add notes, links, and coordinates so nothing is forgotten.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="rounded-2 bg-primary bg-opacity-10 text-primary d-inline-flex p-3 mb-3">
                                <i class="bi bi-currency-dollar fs-3"></i>
                            </div>
                            <h3 class="h5 fw-semibold mb-2">Track expenses</h3>
                            <p class="text-secondary mb-0 small">Log costs per item and see totals. Keep budgets clear for the whole trip.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="rounded-2 bg-primary bg-opacity-10 text-primary d-inline-flex p-3 mb-3">
                                <i class="bi bi-people fs-3"></i>
                            </div>
                            <h3 class="h5 fw-semibold mb-2">Invite others</h3>
                            <p class="text-secondary mb-0 small">Share trips with friends or family. Everyone can view and contribute in one place.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="py-5 bg-primary text-white">
        <div class="container py-4 text-center">
            <h2 class="h2 fw-bold mb-3">Start planning your next trip</h2>
            <p class="lead mb-4 opacity-90">Create a trip in seconds and invite others to contribute.</p>
            @auth
                <a class="btn btn-light btn-lg px-4" href="{{ route('dashboard') }}">Open Dashboard</a>
            @else
                <a class="btn btn-light btn-lg px-4" href="{{ route('register') }}">Get started free</a>
            @endauth
        </div>
    </section>

    {{-- Footer --}}
    <footer class="py-4 bg-dark text-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <span class="fw-semibold">{{ config('app.name') }}</span>
                <span class="small opacity-75">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</span>
            </div>
        </div>
    </footer>
@endsection
