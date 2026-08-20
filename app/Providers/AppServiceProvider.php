<?php

namespace App\Providers;

use App\Support\Analytics;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Login/registration happen outside of any Livewire component (plain
        // Fortify controllers, or the Socialite callback), so there's no
        // component to dispatch a browser event from — queue them for the
        // next page instead. See App\Support\Analytics.
        Event::listen(function (Login $event) {
            Analytics::queue('login', [
                'method' => request()->routeIs('socialite.callback') ? 'google' : 'password',
            ]);
        });

        Event::listen(function (Registered $event) {
            Analytics::queue('sign_up', [
                'method' => request()->routeIs('socialite.callback') ? 'google' : 'password',
            ]);
        });
    }
}
