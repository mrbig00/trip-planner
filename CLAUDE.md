# Trip Planner

Laravel 12 app. Runs locally via Laravel Sail (Docker) — see README.md for setup.

Use `./vendor/bin/sail` to run PHP/Composer/Artisan/npm commands (e.g. `sail artisan migrate`, `sail composer require ...`, `sail npm run dev`) instead of calling `php`, `composer`, or `npm` directly, since the app runs inside Docker containers, not on the host.

## Code quality

- Follow Laravel and PHP best practices and idioms (PSR-12, framework conventions) rather than ad-hoc patterns.
- Favor clean, simple design: clear naming, small focused classes/methods, no unnecessary abstraction.
- UI work should prioritize a polished, intuitive UX — sensible layout, accessible markup, responsive behavior, and clear feedback/states (loading, empty, error).

## Git

- Do not add a `Co-Authored-By: Claude` line (or similar) to commit messages.
