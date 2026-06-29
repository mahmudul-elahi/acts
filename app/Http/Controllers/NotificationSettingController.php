<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationSetting\UpdateNotificationSettingsRequest;
use App\Http\Resources\NotificationSettingResource;
use App\Services\NotificationSettingService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('User - Notifications Settings')]
class NotificationSettingController extends Controller
{
    public function __construct(private NotificationSettingService $notificationSettings) {}

    #[Endpoint(title: 'Notification Settings', description: "Get the authenticated user's notification preferences.")]
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(
            data: new NotificationSettingResource($this->notificationSettings->for($request->user())),
        );
    }

    #[Endpoint(title: 'Update Notification Settings', description: "Update the authenticated user's notification preferences.")]
    public function update(UpdateNotificationSettingsRequest $request): JsonResponse
    {
        $settings = $this->notificationSettings->update($request->user(), $request->validated());

        return $this->successResponse(
            data: new NotificationSettingResource($settings),
            message: 'Notification settings updated successfully.',
        );
    }
}
