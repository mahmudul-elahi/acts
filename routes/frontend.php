<?php

use App\Http\Controllers\User\AdController;
use App\Http\Controllers\User\DigController;
use App\Http\Controllers\User\JournalController;
use App\Http\Controllers\User\JournalTagController;
use App\Http\Controllers\User\Murmuration\CommentController;
use App\Http\Controllers\User\Murmuration\PostController;
use App\Http\Controllers\User\Murmuration\TopicController;
use App\Http\Controllers\User\NotificationController;
use App\Http\Controllers\User\NotificationSettingController;
use App\Http\Controllers\User\QuoteController;
use App\Http\Controllers\User\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('ads')->group(function (): void {
        Route::get('next', [AdController::class, 'next']);
    });

    Route::prefix('digs')->group(function (): void {
        Route::get('today', [DigController::class, 'today']);
        Route::get('stats', [DigController::class, 'stats']);
        Route::get('{dig}', [DigController::class, 'show']);
        Route::post('{dig}/layers/{layer}', [DigController::class, 'submitLayer'])->scopeBindings();
    });

    Route::prefix('notification-settings')->group(function (): void {
        Route::get('/', [NotificationSettingController::class, 'show']);
        Route::match(['put', 'patch'], '/', [NotificationSettingController::class, 'update']);
    });

    Route::prefix('notifications')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('read-all', [NotificationController::class, 'markAllRead']);
        Route::post('{notification}/read', [NotificationController::class, 'markRead']);
    });

    Route::prefix('quotes')->group(function (): void {
        Route::get('/', [QuoteController::class, 'index']);
        Route::post('/', [QuoteController::class, 'store']);
        Route::get('favorites', [QuoteController::class, 'favorites']);
        Route::get('{quote}', [QuoteController::class, 'show']);
        Route::match(['put', 'patch'], '{quote}', [QuoteController::class, 'update']);
        Route::delete('{quote}', [QuoteController::class, 'destroy']);
        Route::post('{quote}/favorite', [QuoteController::class, 'favorite']);
    });

    Route::get('subscription-plans', [SubscriptionController::class, 'plans']);

    Route::prefix('subscription')->group(function (): void {
        Route::get('/', [SubscriptionController::class, 'status']);
        Route::post('checkout', [SubscriptionController::class, 'checkout']);
        Route::post('cancel', [SubscriptionController::class, 'cancel']);
        Route::post('resume', [SubscriptionController::class, 'resume']);
    });

    Route::prefix('journals')->group(function (): void {
        Route::get('tags', [JournalTagController::class, 'index']);

        Route::get('/', [JournalController::class, 'index']);
        Route::post('/', [JournalController::class, 'store']);
        Route::get('favorites', [JournalController::class, 'favorites']);
        Route::get('{journal}', [JournalController::class, 'show']);
        Route::match(['put', 'patch'], '{journal}', [JournalController::class, 'update']);
        Route::delete('{journal}', [JournalController::class, 'destroy']);
        Route::post('{journal}/favorite', [JournalController::class, 'favorite']);
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
