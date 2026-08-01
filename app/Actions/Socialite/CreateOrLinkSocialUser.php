<?php

namespace App\Actions\Socialite;

use App\Models\User;
use App\Models\UserProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class CreateOrLinkSocialUser
{
    /**
     * Find, link, or create a local user for the given Socialite user.
     */
    public function handle(string $provider, SocialiteUser $socialiteUser): User
    {
        $linked = UserProvider::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($linked) {
            return $linked->user;
        }

        $user = User::query()->where('email', $socialiteUser->getEmail())->first();

        if (! $user) {
            [$firstName, $lastName] = $this->splitName(
                $socialiteUser->getName() ?: $socialiteUser->getNickname() ?: $socialiteUser->getEmail()
            );

            $user = User::forceCreate([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $socialiteUser->getEmail(),
                'email_verified_at' => now(),
            ]);
        } elseif (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->providers()->create([
            'provider' => $provider,
            'provider_id' => $socialiteUser->getId(),
        ]);

        return $user;
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
