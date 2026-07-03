<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\EmailVerifiedNotification;

class EmailVerificationService
{
    public function __construct(protected OtpService $otpService) {}

    /**
     * @param  array{email: string, otp: string}  $data
     */
    public function verify(array $data): bool
    {
        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return false;
        }

        if (! $this->otpService->verify($data['email'], $data['otp'], 'email_validation')) {
            return false;
        }

        $user->email_verified_at = now();
        $user->save();

        $user->notify(new EmailVerifiedNotification);

        return true;
    }
}
