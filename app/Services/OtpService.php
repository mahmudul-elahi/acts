<?php

namespace App\Services;

use App\Models\Otp;
use App\Notifications\SendOtpNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

class OtpService
{
    public function generateAndSend(string $email, string $type): void
    {
        $otp = (string) rand(10000, 99999);

        Otp::updateOrCreate(
            ['email' => $email, 'type' => $type],
            [
                'otp' => $otp,
                'expires_at' => Carbon::now()->addMinutes(10),
            ]
        );

        Notification::route('mail', $email)->notify(new SendOtpNotification($otp, $type));
    }

    public function verify(string $email, string $otp, string $type): bool
    {
        $otpRecord = Otp::where('email', $email)
            ->where('otp', $otp)
            ->where('type', $type)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($otpRecord) {
            $otpRecord->delete();

            return true;
        }

        return false;
    }

    public function check(string $email, string $otp, string $type): bool
    {
        return Otp::where('email', $email)
            ->where('otp', $otp)
            ->where('type', $type)
            ->where('expires_at', '>', Carbon::now())
            ->exists();
    }
}
