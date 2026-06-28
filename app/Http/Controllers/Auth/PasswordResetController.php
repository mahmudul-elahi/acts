<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SendPasswordResetOtpRequest;
use App\Http\Requests\Auth\VerifyPasswordResetOtpRequest;
use App\Services\OtpService;
use App\Services\PasswordResetService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Auth')]
class PasswordResetController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
        protected PasswordResetService $passwordResetService,
    ) {}

    #[Endpoint(title: 'Send Password Reset OTP', description: 'Send a password reset OTP to the user\'s email.')]
    public function send(SendPasswordResetOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->otpService->generateAndSend($validated['email'], 'password_reset');

        return $this->successResponse(message: 'OTP sent to your email.');
    }

    #[Endpoint(title: 'Resend Password Reset OTP', description: 'Resend a password reset OTP to the user\'s email.')]
    public function resend(SendPasswordResetOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->otpService->generateAndSend($validated['email'], 'password_reset');

        return $this->successResponse(message: 'OTP resent to your email.');
    }

    #[Endpoint(title: 'Verify Password Reset OTP', description: 'Verify the password reset OTP.')]
    public function verify(VerifyPasswordResetOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($this->otpService->check($validated['email'], $validated['otp'], 'password_reset')) {
            return $this->successResponse(message: 'OTP verified. You can now reset your password.');
        }

        return $this->errorResponse(message: 'Invalid or expired OTP.');
    }

    #[Endpoint(title: 'Reset Password', description: "Reset the user's password after OTP verification.")]
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! $this->passwordResetService->reset($validated)) {
            return $this->errorResponse(message: 'Invalid or expired OTP.');
        }

        return $this->successResponse(message: 'Password reset successfully.');
    }
}
