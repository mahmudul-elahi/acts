<?php

use App\Http\Controllers\NotificationSettingController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('notification-settings')->group(function (): void {
        Route::get('/', [NotificationSettingController::class, 'show']);
        Route::match(['put', 'patch'], '/', [NotificationSettingController::class, 'update']);
    });

    Route::get('subscription-plans', [SubscriptionController::class, 'plans']);

    Route::prefix('subscription')->group(function (): void {
        Route::get('/', [SubscriptionController::class, 'status']);
        Route::post('checkout', [SubscriptionController::class, 'checkout']);
        Route::post('cancel', [SubscriptionController::class, 'cancel']);
        Route::post('resume', [SubscriptionController::class, 'resume']);
    });
});
