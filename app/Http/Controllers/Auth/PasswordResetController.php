<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SendPasswordResetOtpRequest;
use App\Http\Requests\Auth\VerifyPasswordResetOtpRequest;
use App\Services\OtpService;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
        protected PasswordResetService $passwordResetService,
    ) {}

    /**
     * Send a password reset OTP to the user's email.
     */
    public function send(SendPasswordResetOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->otpService->generateAndSend($validated['email'], 'password_reset');

        return $this->successResponse(message: 'OTP sent to your email.');
    }

    /**
     * Resend a password reset OTP to the user's email.
     */
    public function resend(SendPasswordResetOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->otpService->generateAndSend($validated['email'], 'password_reset');

        return $this->successResponse(message: 'OTP resent to your email.');
    }

    /**
     * Verify the password reset OTP.
     */
    public function verify(VerifyPasswordResetOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($this->otpService->check($validated['email'], $validated['otp'], 'password_reset')) {
            return $this->successResponse(message: 'OTP verified. You can now reset your password.');
        }

        return $this->errorResponse(message: 'Invalid or expired OTP.');
    }

    /**
     * Reset the user's password after OTP verification.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! $this->passwordResetService->reset($validated)) {
            return $this->errorResponse(message: 'Invalid or expired OTP.');
        }

        return $this->successResponse(message: 'Password reset successfully.');
    }
}
