<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\VerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', RegisterController::class)->middleware('throttle:6,1');
    Route::post('login', LoginController::class)->middleware('throttle:10,1');
    Route::post('social', SocialAuthController::class)->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [ProfileController::class, 'show']);
        // POST is included because PHP only parses multipart/form-data (file uploads) on POST.
        Route::match(['post', 'put', 'patch'], 'profile', [ProfileController::class, 'update']);
        Route::delete('profile/avatar', [ProfileController::class, 'deleteAvatar']);
        Route::put('password', [ProfileController::class, 'updatePassword']);
        Route::post('logout', LogoutController::class);
    });

    Route::prefix('verify')->group(function (): void {
        Route::post('/', [VerificationController::class, 'verify'])->middleware('throttle:6,1');
        Route::post('resend', [VerificationController::class, 'resend'])->middleware('throttle:3,1');
    });

    Route::prefix('password')->group(function (): void {
        Route::post('send-otp', [PasswordResetController::class, 'send'])->middleware('throttle:3,1');
        Route::post('resend-otp', [PasswordResetController::class, 'resend'])->middleware('throttle:3,1');
        Route::post('verify-otp', [PasswordResetController::class, 'verify'])->middleware('throttle:6,1');
        Route::post('reset', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1');
    });
});
