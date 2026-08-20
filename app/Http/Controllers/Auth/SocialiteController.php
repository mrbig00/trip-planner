<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Auth\Events\Registered;
use Laravel\Socialite\Facades\Socialite;
use App\Actions\Socialite\CreateOrLinkSocialUser;
use App\Exceptions\SocialiteAuthenticationException;

class SocialiteController extends Controller
{
    /**
     * Redirect the user to the OAuth provider's consent screen.
     */
    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the OAuth provider's callback, log the user in, and redirect home.
     */
    public function callback(string $provider, CreateOrLinkSocialUser $createOrLinkSocialUser): RedirectResponse
    {
        $socialiteUser = Socialite::driver($provider)->user();

        try {
            $user = $createOrLinkSocialUser->handle($provider, $socialiteUser);
        } catch (SocialiteAuthenticationException $exception) {
            return redirect()->route('login')->withErrors(['email' => $exception->getMessage()]);
        }

        // CreateOrLinkSocialUser creates new users directly (forceCreate),
        // so this event doesn't fire on its own the way it does for a
        // password registration — fire it here so a first-time Google
        // sign-in is still tracked as a sign_up (see AppServiceProvider).
        if ($user->wasRecentlyCreated) {
            event(new Registered($user));
        }

        Auth::login($user, remember: true);

        return redirect()->intended(config('fortify.home'));
    }
}
