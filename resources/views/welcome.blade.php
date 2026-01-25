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
                        <li class="nav-item">
                            <a class="btn btn-outline-light btn-sm" href="{{ route('trips.index') }}">My trips</a>
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

    @php
        $undraw = 'https://cdn.jsdelivr.net/gh/balazser/undraw-svg-collection@main/svgs';
    @endphp
    {{-- Hero (Bootstrap masthead–style gradient: getbootstrap.com .bd-masthead) --}}
    <section class="bd-masthead text-white">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <p class="fw-semibold small text-uppercase mb-2 opacity-75">Plan together, travel better</p>
                    <h1 class="display-4 fw-bold mb-4">Plan your trips in one place</h1>
                    <p class="lead mb-4 opacity-90">
                        Create trips, add locations, track expenses, and invite others—so everyone stays on the same page from idea to destination.
                    </p>
                    @auth
                        <a class="btn btn-primary btn-lg px-4" href="{{ route('dashboard') }}">Go to Dashboard</a>
                    @else
                        <a class="btn btn-primary btn-lg px-4 me-2" href="{{ route('register') }}">Get started</a>
                        <a class="btn btn-outline-light btn-lg px-4" href="{{ route('login') }}">Log in</a>
                    @endauth
                </div>
                <div class="col-lg-6 text-center pt-4 pt-lg-0">
                    <img src="{{ $undraw }}/around-the-world.svg" alt="Travel the world" class="img-fluid undraw-dark" style="max-height: 320px;" width="480" height="320" loading="eager">
                </div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section class="py-5 bg-dark">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="h1 fw-bold mb-3 text-white">Everything you need to plan a trip</h2>
                <p class="lead text-secondary">Simple tools that help you and your group stay organized.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 bg-dark border border-secondary">
                        <div class="card-body p-4 text-center text-light">
                            <img src="{{ $undraw }}/master-plan.svg" alt="" class="img-fluid mb-3 undraw-dark" style="max-height: 120px;" width="160" height="120" loading="lazy">
                            <h3 class="h5 fw-semibold mb-2">Plan trips</h3>
                            <p class="text-secondary mb-0 small">Create a trip, set dates, and add a description. One place for all the details.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 bg-dark border border-secondary">
                        <div class="card-body p-4 text-center text-light">
                            <img src="{{ $undraw }}/map.svg" alt="" class="img-fluid mb-3 undraw-dark" style="max-height: 120px;" width="160" height="120" loading="lazy">
                            <h3 class="h5 fw-semibold mb-2">Add locations</h3>
                            <p class="text-secondary mb-0 small">Save places you want to see. Add notes, links, and coordinates so nothing is forgotten.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 bg-dark border border-secondary">
                        <div class="card-body p-4 text-center text-light">
                            <img src="{{ $undraw }}/receipt.svg" alt="" class="img-fluid mb-3 undraw-dark" style="max-height: 120px;" width="160" height="120" loading="lazy">
                            <h3 class="h5 fw-semibold mb-2">Track expenses</h3>
                            <p class="text-secondary mb-0 small">Log costs per item and see totals. Keep budgets clear for the whole trip.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 bg-dark border border-secondary">
                        <div class="card-body p-4 text-center text-light">
                            <img src="{{ $undraw }}/collaborators.svg" alt="" class="img-fluid mb-3 undraw-dark" style="max-height: 120px;" width="160" height="120" loading="lazy">
                            <h3 class="h5 fw-semibold mb-2">Invite others</h3>
                            <p class="text-secondary mb-0 small">Share trips with friends or family. Everyone can view and contribute in one place.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="py-5 bg-dark text-white position-relative overflow-hidden border-top border-secondary">
        <div class="container py-4 text-center position-relative">
            <img src="{{ $undraw }}/celebrating.svg" alt="" class="img-fluid mb-3 opacity-75" style="max-height: 160px; filter: brightness(0) invert(1);" width="240" height="160" loading="lazy">
            <h2 class="h2 fw-bold mb-3">Start planning your next trip</h2>
            <p class="lead mb-4 text-secondary">Create a trip in seconds and invite others to contribute.</p>
            @auth
                <a class="btn btn-primary btn-lg px-4" href="{{ route('dashboard') }}">Open Dashboard</a>
            @else
                <a class="btn btn-primary btn-lg px-4" href="{{ route('register') }}">Get started free</a>
            @endauth
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
