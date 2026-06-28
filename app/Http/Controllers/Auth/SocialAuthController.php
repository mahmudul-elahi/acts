<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SocialLoginRequest;
use App\Http\Resources\Auth\UserResource;
use App\Services\SocialAuthService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Auth')]
class SocialAuthController extends Controller
{
    public function __construct(protected SocialAuthService $socialAuthService) {}

    #[Endpoint(title: 'Social Login', description: 'Authenticate with a social provider (Google or Apple) using an OAuth token.')]
    public function __invoke(SocialLoginRequest $request): JsonResponse
    {
        $result = $this->socialAuthService->authenticate($request->validated());

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
