<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserListResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Admin - User Managements')]
class UserManagementController extends Controller
{
    #[Endpoint(title: 'List Users', description: 'Get a paginated list of users.')]
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('roles')
            ->paginate(perPage: $request->integer('per_page', 15));

        return $this->paginatedResponse(UserListResource::collection($users));
    }

    #[Endpoint(title: 'Toggle User Status', description: 'Enable or disable a user account.')]
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
