<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\VerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', RegisterController::class);
    Route::post('login', LoginController::class);
    Route::post('social', SocialAuthController::class);

    Route::get('me', ProfileController::class)->middleware('auth:sanctum');

    Route::prefix('verify')->group(function (): void {
        Route::post('send', [VerificationController::class, 'send']);
        Route::post('resend', [VerificationController::class, 'resend']);
        Route::post('verify', [VerificationController::class, 'verify']);
    });

    Route::prefix('password')->group(function (): void {
        Route::post('send-otp', [PasswordResetController::class, 'send']);
        Route::post('resend-otp', [PasswordResetController::class, 'resend']);
        Route::post('verify-otp', [PasswordResetController::class, 'verify']);
        Route::post('reset', [PasswordResetController::class, 'reset']);
    });
});
