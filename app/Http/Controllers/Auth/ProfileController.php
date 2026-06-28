<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\Auth\UserResource;
use App\Services\ProfileService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Auth')]
class ProfileController extends Controller
{
    public function __construct(private ProfileService $profile) {}

    #[Endpoint(title: 'Profile', description: "Get the authenticated user's profile.")]
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(
            data: new UserResource($request->user()),
        );
    }

    #[Endpoint(title: 'Update Profile', description: 'Update the authenticated user\'s name, email and photo. Accepts multipart/form-data with an optional avatar upload.')]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profile->update(
            $request->user(),
            $request->safe()->except('avatar'),
            $request->file('avatar'),
        );

        return $this->successResponse(
            data: new UserResource($user),
            message: 'Profile updated successfully.',
        );
    }

    #[Endpoint(title: 'Delete Profile Photo', description: 'Remove the authenticated user\'s profile photo.')]
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $this->profile->deleteAvatar($request->user());

        return $this->successResponse(
            data: new UserResource($user),
            message: 'Profile photo removed successfully.',
        );
    }

    #[Endpoint(title: 'Update Password', description: 'Change the authenticated user\'s password.')]
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $this->profile->updatePassword($request->user(), $request->validated('password'));

        return $this->successResponse(
            message: 'Password updated successfully.',
        );
    }
}
