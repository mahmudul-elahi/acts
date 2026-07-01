<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Exception;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;

class SocialAuthService
{
    /**
     * @param  array{provider: string, token: string, device_name?: string|null}  $credentials
     * @return array{user: User, token: string}
     */
    public function authenticate(array $credentials): array
    {
        $provider = $credentials['provider'];

        $socialiteUser = $this->fetchUserFromToken($provider, $credentials['token']);

        $user = User::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if (! $user) {
            $user = User::query()->where('email', $socialiteUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'provider' => $provider,
                    'provider_id' => $socialiteUser->getId(),
                ]);
            } else {
                $fullName = $socialiteUser->getName() ?? $socialiteUser->getNickname() ?? 'User';
                [$firstName, $lastName] = $this->splitName($fullName);

                $user = User::query()->create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $socialiteUser->getEmail(),
                    'provider' => $provider,
                    'provider_id' => $socialiteUser->getId(),
                    'avatar' => $socialiteUser->getAvatar(),
                    'password' => Str::random(32),
                    'email_verified_at' => now(),
                ]);

                $user->assignRole(Role::findOrCreate(UserRole::User->value));
            }
        }

        $tokenName = $credentials['device_name'] ?? 'social-api-token';
        $token = $user->createToken($tokenName)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Split a provider-supplied full name into first and last name parts.
     *
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * @throws Exception
     */
    private function fetchUserFromToken(string $provider, string $token): SocialiteUser
    {
        try {
            return Socialite::driver($provider)->stateless()->userFromToken($token);
        } catch (Exception $e) {
            throw new Exception("Invalid {$provider} token: {$e->getMessage()}");
        }
    }
}
