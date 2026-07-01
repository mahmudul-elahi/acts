<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\NotificationResource;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('User - Notifications')]
class NotificationController extends Controller
{
    #[Endpoint(title: 'List Notifications', description: 'Get the authenticated user\'s notifications, newest first, each flagged read or unread.')]
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->cursorPaginate(perPage: $this->perPage($request))
            ->withQueryString();

        return $this->cursorPaginatedResponse(NotificationResource::collection($notifications));
    }

    #[Endpoint(title: 'Unread Count', description: 'Get the number of unread notifications, for the bell badge.')]
    public function unreadCount(Request $request): JsonResponse
    {
        return $this->successResponse(
            data: ['unread_count' => $request->user()->unreadNotifications()->count()],
        );
    }

    #[Endpoint(title: 'Mark Notification Read', description: 'Mark a single notification as read.')]
    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()->find($notification);

        if ($record === null) {
            return $this->errorResponse(message: 'Notification not found.', status: Response::HTTP_NOT_FOUND);
        }

        $record->markAsRead();

        return $this->successResponse(
            data: new NotificationResource($record),
            message: 'Notification marked as read.',
        );
    }

    #[Endpoint(title: 'Mark All Read', description: 'Mark every unread notification as read.')]
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->successResponse(message: 'All notifications marked as read.');
    }
}
