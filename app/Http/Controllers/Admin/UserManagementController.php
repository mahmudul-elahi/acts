<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserListResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('roles')
            ->paginate(perPage: $request->integer('per_page', 15));

        return $this->paginatedResponse(UserListResource::collection($users));
    }

    public function toggle(User $user): JsonResponse
    {
        $user->update(['status' => ! $user->status]);

        $message = $user->status ? 'User enabled successfully.' : 'User disabled successfully.';

        return $this->successResponse(
            data: new UserListResource($user->load('roles')),
            message: $message,
        );
    }
}
