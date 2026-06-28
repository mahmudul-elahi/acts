<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserListResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

#[Group('Admin - User Managements')]
class UserManagementController extends Controller
{
    #[Endpoint(title: 'List Users', description: 'Get a paginated list of users. Filter by signup date (filter[created_date]=2026-06-28) and by status (filter[status]=active|deactive|all, defaults to all).')]
    public function index(Request $request): JsonResponse
    {
        $users = QueryBuilder::for(
            User::query()
                ->with('roles')
                ->withoutRole(UserRole::Admin->value)
        )
            ->allowedFilters(
                AllowedFilter::scope('created_date'),
                AllowedFilter::scope('status'),
            )
            ->defaultSort('-created_at')
            ->paginate(perPage: $request->integer('per_page', 15))
            ->appends($request->query());

        return $this->paginatedResponse(UserListResource::collection($users));
    }

    #[Endpoint(title: 'Toggle User Status', description: 'Enable or disable a user account.')]
    public function toggle(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            return $this->errorResponse(
                message: 'You cannot change your own account status.',
                status: HttpResponse::HTTP_FORBIDDEN,
            );
        }

        $user->update(['status' => ! $user->status]);

        $message = $user->status ? 'User enabled successfully.' : 'User disabled successfully.';

        return $this->successResponse(
            data: new UserListResource($user->load('roles')),
            message: $message,
        );
    }
}
