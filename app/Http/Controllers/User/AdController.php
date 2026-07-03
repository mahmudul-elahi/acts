<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\AdResource;
use App\Services\AdService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('User - Ads')]
class AdController extends Controller
{
    public function __construct(private AdService $ads) {}

    #[Endpoint(title: 'Next Ad', description: 'Get a random active ad the user has not seen recently. Returns null if none available.')]
    public function next(Request $request): JsonResponse
    {
        $ad = $this->ads->next($request->user());

        return $this->successResponse(
            data: $ad ? new AdResource($ad) : null,
        );
    }
}
