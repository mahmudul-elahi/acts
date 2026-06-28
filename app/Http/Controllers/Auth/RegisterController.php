<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\OtpService;
use App\Services\RegistrationService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Auth')]
class RegisterController extends Controller
{
    public function __construct(
        protected RegistrationService $registrationService,
        protected OtpService $otpService,
    ) {}

    #[Endpoint(title: 'Register', description: 'Register a new user account.')]
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = $this->registrationService->register($request->validated());

        $this->otpService->generateAndSend($user->email, 'email_validation');

        return $this->createdResponse(message: 'Registered successfully. Please verify your email with the OTP sent.');
    }
}
