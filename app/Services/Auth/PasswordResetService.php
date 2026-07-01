<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PasswordResetService
{
    public function __construct(protected OtpService $otpService) {}

    /**
     * @param  array{email: string, otp: string, password: string}  $data
     */
    public function reset(array $data): bool
    {
        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return false;
        }

        if (! $this->otpService->verify($data['email'], $data['otp'], 'password_reset')) {
            return false;
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        return true;
    }
}
