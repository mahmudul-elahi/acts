<?php

use App\Http\Controllers\NotificationSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('notification-settings')->group(function (): void {
        Route::get('/', [NotificationSettingController::class, 'show']);
        Route::match(['put', 'patch'], '/', [NotificationSettingController::class, 'update']);
    });
});
