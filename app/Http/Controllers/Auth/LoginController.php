<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        /** @var array{email: string, password: string, device_name?: string} $validated */
        $validated = $request->validated();

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse(
                message: 'The provided credentials are incorrect.',
                status: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
                errors: [
                    'email' => ['The provided credentials are incorrect.'],
                ],
            );
        }

        $token = $user->createToken($this->tokenName($validated))
            ->plainTextToken;

        return $this->successResponse(
            data: [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            message: 'Logged in successfully.',
        );
    }

    /**
     * @param  array{device_name?: string}  $validated
     */
    private function tokenName(array $validated): string
    {
        return $validated['device_name'] ?? 'api-token';
    }
}
