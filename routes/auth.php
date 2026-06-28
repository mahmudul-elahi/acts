<?php

use App\Http\Controllers\Auth\LoginController;
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

    Route::get('me', ProfileController::class)->middleware('auth:sanctum');

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
