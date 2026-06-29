<?php

use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\Admin\DigController;
use App\Http\Controllers\Admin\MurmurationPostController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function (): void {
    Route::prefix('users')->group(function (): void {
        Route::get('/', [UserManagementController::class, 'index']);
        Route::post('{user}/toggle-status', [UserManagementController::class, 'toggle']);
    });

    Route::prefix('quotes')->group(function (): void {
        Route::get('/', [QuoteController::class, 'index']);
        Route::post('/', [QuoteController::class, 'store']);
        Route::post('bulk-upload', [QuoteController::class, 'bulkUpload']);
        Route::get('{quote}', [QuoteController::class, 'show']);
        Route::match(['put', 'patch'], '{quote}', [QuoteController::class, 'update']);
        Route::delete('{quote}', [QuoteController::class, 'destroy']);
    });

    Route::prefix('digs')->group(function (): void {
        Route::get('/', [DigController::class, 'index']);
        Route::post('/', [DigController::class, 'store']);
        Route::get('{dig}', [DigController::class, 'show']);
        Route::match(['put', 'patch'], '{dig}', [DigController::class, 'update']);
        Route::delete('{dig}', [DigController::class, 'destroy']);
    });

    Route::prefix('ads')->group(function (): void {
        Route::get('/', [AdController::class, 'index']);
        Route::post('/', [AdController::class, 'store']);
        Route::post('{ad}/toggle-status', [AdController::class, 'toggle']);
        Route::get('{ad}', [AdController::class, 'show']);
        Route::match(['put', 'patch'], '{ad}', [AdController::class, 'update']);
        Route::delete('{ad}', [AdController::class, 'destroy']);
    });

    Route::prefix('subscription-plans')->group(function (): void {
        Route::get('/', [SubscriptionPlanController::class, 'index']);
        Route::post('/', [SubscriptionPlanController::class, 'store']);
        Route::get('{subscriptionPlan}', [SubscriptionPlanController::class, 'show']);
        Route::match(['put', 'patch'], '{subscriptionPlan}', [SubscriptionPlanController::class, 'update']);
        Route::delete('{subscriptionPlan}', [SubscriptionPlanController::class, 'destroy']);
    });

    Route::prefix('payments')->group(function (): void {
        Route::get('/', [PaymentController::class, 'index']);
        Route::get('overview', [PaymentController::class, 'overview']);
    });

    Route::prefix('murmuration-posts')->group(function (): void {
        Route::get('/', [MurmurationPostController::class, 'index']);
        Route::get('{murmurationPost}', [MurmurationPostController::class, 'show']);
        Route::post('{murmurationPost}/toggle-status', [MurmurationPostController::class, 'toggle']);
        Route::delete('{murmurationPost}', [MurmurationPostController::class, 'destroy']);
    });
});
