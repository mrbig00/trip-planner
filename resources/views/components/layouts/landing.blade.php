<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">

    <title>{{ $title ?? config('app.name') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <style>
        [data-bs-theme="dark"] img.undraw-dark { filter: brightness(1.15); }
        /*
         * Bootstrap getbootstrap.com .bd-masthead (site/src/scss/_masthead.scss)
         * Body base + radial “orbs” in primary, accent, violet, pink.
         */
        .bd-masthead {
            --bd-body-rgb: 33, 37, 41;
            --bd-primary-rgb: 76, 11, 206;
            --bd-accent-rgb: 255, 228, 132;
            --bd-violet-rgb: 111, 66, 193;
            --bd-pink-rgb: 214, 51, 132;
            padding: 3rem 0;
            background-color: rgb(var(--bd-body-rgb));
            background-image:
                linear-gradient(180deg, rgba(var(--bd-body-rgb), 0.01) 0%, rgba(var(--bd-body-rgb), 1) 85%),
                radial-gradient(ellipse at top left, rgba(var(--bd-primary-rgb), 0.5), transparent 50%),
                radial-gradient(ellipse at top right, rgba(var(--bd-accent-rgb), 0.5), transparent 50%),
                radial-gradient(ellipse at center right, rgba(var(--bd-violet-rgb), 0.5), transparent 50%),
                radial-gradient(ellipse at center left, rgba(var(--bd-pink-rgb), 0.5), transparent 50%);
        }
        @media (min-width: 768px) {
            .bd-masthead { padding: 4rem 0; }
        }
    </style>
</head>
<body class="bg-dark text-light">
    @yield('content')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    @stack('scripts')
</body>
</html>
