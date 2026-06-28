<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\UserResource;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Auth')]
class ProfileController extends Controller
{
    #[Endpoint(title: 'Profile', description: "Get the authenticated user's profile.")]
    public function __invoke(Request $request): JsonResponse
    {
        return $this->successResponse(
            data: new UserResource($request->user()),
        );
    }
}
