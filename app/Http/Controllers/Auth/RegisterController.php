<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RegisterController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        /** @var array{name: string, email: string, password: string, device_name?: string} $validated */
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $role = Role::findOrCreate(
                UserRole::User->value,
                $this->roleGuardName(),
            );

            $user->assignRole($role);

            return $user;
        });

        $token = $user->createToken($this->tokenName($validated))
            ->plainTextToken;

        return $this->createdResponse(
            data: [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            message: 'Registered successfully.',
        );
    }

    /**
     * @param  array{device_name?: string}  $validated
     */
    private function tokenName(array $validated): string
    {
        return $validated['device_name'] ?? 'api-token';
    }

    private function roleGuardName(): string
    {
        return (string) config('auth.defaults.guard', 'web');
    }
}
