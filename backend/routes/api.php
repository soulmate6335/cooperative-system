<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MemberApplicationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('membership/applications', [MemberApplicationController::class, 'store'])->middleware('throttle:registration');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::prefix('admin/membership/applications')->group(function (): void {
            Route::get('/', [MemberApplicationController::class, 'index']);
            Route::get('/{application}', [MemberApplicationController::class, 'show']);
            Route::post('/{application}/approve', [MemberApplicationController::class, 'approve']);
            Route::post('/{application}/reject', [MemberApplicationController::class, 'reject']);
        });
    });
});
