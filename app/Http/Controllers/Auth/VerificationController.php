<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendEmailVerificationOtpRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Services\EmailVerificationService;
use App\Services\OtpService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Auth')]
class VerificationController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
        protected EmailVerificationService $emailVerificationService,
    ) {}

    #[Endpoint(title: 'Send Verification OTP', description: 'Send an email verification OTP to the user\'s email.')]
    public function send(SendEmailVerificationOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->otpService->generateAndSend($validated['email'], 'email_validation');

        return $this->successResponse(message: 'OTP sent to your email.');
    }

    #[Endpoint(title: 'Resend Verification OTP', description: 'Resend an email verification OTP to the user\'s email.')]
    public function resend(SendEmailVerificationOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->otpService->generateAndSend($validated['email'], 'email_validation');

        return $this->successResponse(message: 'OTP resent to your email.');
    }

    #[Endpoint(title: 'Verify Email', description: "Verify the user's email using the OTP.")]
    public function verify(VerifyEmailRequest $request): JsonResponse
    {
        if (! $this->emailVerificationService->verify($request->validated())) {
            return $this->errorResponse(message: 'Invalid or expired OTP.');
        }

        return $this->successResponse(message: 'Email verified successfully.');
    }
}
