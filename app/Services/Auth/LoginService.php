<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginService
{
    public function __construct(protected OtpService $otpService) {}

    /**
     * @param  array{email: string, password: string, device_name?: string}  $credentials
     * @return array{user: User, token: string}|array{needs_verification: true}|null
     */
    public function attempt(array $credentials): ?array
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        if (! $user->status) {
            return ['disabled' => true];
        }

        if (! $user->hasVerifiedEmail()) {
            $this->otpService->generateAndSend($user->email, 'email_validation');

            return ['needs_verification' => true];
        }

        $tokenName = $credentials['device_name'] ?? 'api-token';
        $token = $user->createToken($tokenName)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
