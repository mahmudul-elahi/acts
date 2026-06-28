<?php

use App\Http\Controllers\Admin\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function (): void {
    Route::prefix('users')->group(function (): void {
        Route::get('/', [UserManagementController::class, 'index']);
        Route::post('{user}/toggle-status', [UserManagementController::class, 'toggle']);
    });
});
