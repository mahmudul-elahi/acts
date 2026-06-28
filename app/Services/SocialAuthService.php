<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

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

        $user = User::query()->where("{$provider}_id", $socialiteUser->getId())->first();

        if (! $user) {
            $user = User::query()->where('email', $socialiteUser->getEmail())->first();

            if ($user) {
                $user->update(["{$provider}_id" => $socialiteUser->getId()]);
            } else {
                $user = User::query()->create([
                    'first_name' => $socialiteUser->getName() ?? $socialiteUser->getNickname() ?? 'User',
                    'last_name' => '',
                    'email' => $socialiteUser->getEmail(),
                    "{$provider}_id" => $socialiteUser->getId(),
                    'avatar' => $socialiteUser->getAvatar(),
                    'password' => bcrypt(Str::random(32)),
                    'email_verified_at' => now(),
                ]);
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
