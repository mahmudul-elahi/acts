<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return $this->successResponse(
            data: new UserResource($request->user()),
        );
    }
}
