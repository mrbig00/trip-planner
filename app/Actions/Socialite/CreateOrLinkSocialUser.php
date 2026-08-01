<?php

namespace App\Actions\Socialite;

use App\Exceptions\SocialiteAuthenticationException;
use App\Models\User;
use App\Models\UserProvider;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Two\User as SocialiteUser;

class CreateOrLinkSocialUser
{
    /**
     * Find, link, or create a local user for the given Socialite user.
     *
     * @throws SocialiteAuthenticationException if the provider did not share an email address
     */
    public function handle(string $provider, SocialiteUser $socialiteUser): User
    {
        $email = $socialiteUser->getEmail();

        if (! $email) {
            throw new SocialiteAuthenticationException("Your {$provider} account did not share an email address.");
        }

        $providerId = $socialiteUser->getId();
        $emailConfirmedByProvider = $this->emailConfirmedByProvider($provider, $socialiteUser);

        try {
            return DB::transaction(function () use ($provider, $providerId, $email, $socialiteUser, $emailConfirmedByProvider) {
                $linked = UserProvider::query()
                    ->where('provider', $provider)
                    ->where('provider_id', $providerId)
                    ->first();

                if ($linked) {
                    return $linked->user;
                }

                $user = User::query()->where('email', $email)->first();

                if (! $user) {
                    [$firstName, $lastName] = $this->splitName(
                        $socialiteUser->getName() ?: $socialiteUser->getNickname() ?: $email
                    );

                    $user = User::forceCreate([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'email_verified_at' => $emailConfirmedByProvider ? now() : null,
                    ]);
                } elseif ($emailConfirmedByProvider && ! $user->email_verified_at) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                $user->providers()->create([
                    'provider' => $provider,
                    'provider_id' => $providerId,
                ]);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent callback for the same identity won the race and already linked it.
            return UserProvider::query()
                ->where('provider', $provider)
                ->where('provider_id', $providerId)
                ->firstOrFail()
                ->user;
        }
    }

    /**
     * Determine whether the provider itself already confirmed this email address.
     */
    private function emailConfirmedByProvider(string $provider, SocialiteUser $socialiteUser): bool
    {
        return match ($provider) {
            // Google's OpenID Connect userinfo response includes `email_verified`.
            'google' => (bool) ($socialiteUser->getRaw()['email_verified'] ?? false),
            default => false,
        };
    }

    /**
     * Split a display name into first/last, matching the first_name/last_name
     * columns required by the users table.
     *
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
