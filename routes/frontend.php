<?php

use App\Http\Controllers\Murmuration\CommentController;
use App\Http\Controllers\Murmuration\PostController;
use App\Http\Controllers\Murmuration\TopicController;
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

    Route::prefix('murmuration')->group(function (): void {
        Route::get('topics', [TopicController::class, 'index']);

        Route::get('posts', [PostController::class, 'index']);
        Route::post('posts', [PostController::class, 'store']);
        Route::get('posts/saved', [PostController::class, 'saved']);
        Route::get('posts/{post}', [PostController::class, 'show']);
        Route::delete('posts/{post}', [PostController::class, 'destroy']);
        Route::post('posts/{post}/like', [PostController::class, 'like']);
        Route::post('posts/{post}/save', [PostController::class, 'save']);

        Route::get('posts/{post}/comments', [CommentController::class, 'index']);
        Route::post('posts/{post}/comments', [CommentController::class, 'store']);
        Route::post('comments/{comment}/reply', [CommentController::class, 'reply']);
        Route::post('comments/{comment}/like', [CommentController::class, 'like']);
    });
});
