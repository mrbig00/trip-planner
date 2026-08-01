<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Socialite\CreateOrLinkSocialUser;
use App\Exceptions\SocialiteAuthenticationException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

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

        Auth::login($user, remember: true);

        return redirect()->intended(config('fortify.home'));
    }
}
