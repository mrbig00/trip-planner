# Trip Planner

A Laravel application for planning trips.

## Local Development (Laravel Sail)

This project runs locally via [Laravel Sail](https://laravel.com/docs/sail), Laravel's Docker-based development environment.

### Prerequisites

- Docker and Docker Compose

### Setup

```bash
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

The app will be available at http://localhost (or `${APP_PORT}` if set in `.env`).

### Common commands

Run these through Sail instead of calling `php`/`composer`/`npm` directly, so they execute inside the containers:

```bash
./vendor/bin/sail artisan ...
./vendor/bin/sail composer ...
./vendor/bin/sail npm ...
./vendor/bin/sail test
./vendor/bin/sail down
```

Tip: add a `sail` alias to your shell profile to shorten these:

```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

### Services

- **laravel.test** — the app container (PHP 8.5 runtime)
- **pgsql** — PostgreSQL 18
