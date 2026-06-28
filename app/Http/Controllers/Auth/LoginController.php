<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\UserResource;
use App\Services\LoginService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

#[Group('Auth')]
class LoginController extends Controller
{
    public function __construct(protected LoginService $loginService) {}

    #[Endpoint(title: 'Login', description: 'Authenticate a user and return an API token.')]
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $result = $this->loginService->attempt($request->validated());

        if ($result === null) {
            return $this->errorResponse(
                message: 'The provided credentials are incorrect.',
                status: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
                errors: [
                    'email' => ['The provided credentials are incorrect.'],
                ],
            );
        }

        if (isset($result['needs_verification'])) {
            return $this->successResponse(
                data: ['needs_verification' => true],
                message: 'Email verification OTP sent to your email.',
            );
        }

        if (isset($result['disabled'])) {
            return $this->errorResponse(
                message: 'Your account has been disabled.',
                status: HttpResponse::HTTP_FORBIDDEN,
            );
        }

        return $this->successResponse(
            data: [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                'token_type' => 'Bearer',
            ],
            message: 'Logged in successfully.',
        );
    }
}
