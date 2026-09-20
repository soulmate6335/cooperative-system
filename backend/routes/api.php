<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FinancialController;
use App\Http\Controllers\MemberApplicationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('membership/applications', [MemberApplicationController::class, 'store'])->middleware('throttle:registration');
    Route::get('payment-methods', [FinancialController::class, 'paymentMethods']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('member/accounts', [FinancialController::class, 'ownAccounts']);
        Route::get('member/accounts/{account}/transactions', [FinancialController::class, 'accountTransactions']);
        Route::post('finance/payments', [FinancialController::class, 'storePayment']);
        Route::post('finance/payments/{payment}/verify', [FinancialController::class, 'verifyPayment']);
        Route::post('finance/payments/{payment}/receipt', [FinancialController::class, 'issueReceipt']);
        Route::post('finance/transactions/{transaction}/reverse', [FinancialController::class, 'reverseTransaction']);
        Route::post('admin/payment-methods', [FinancialController::class, 'storePaymentMethod']);
        Route::post('admin/financial/accounts', [FinancialController::class, 'storeAccount']);
        Route::prefix('admin/membership/applications')->group(function (): void {
            Route::get('/', [MemberApplicationController::class, 'index']);
            Route::get('/{application}', [MemberApplicationController::class, 'show']);
            Route::post('/{application}/approve', [MemberApplicationController::class, 'approve']);
            Route::post('/{application}/reject', [MemberApplicationController::class, 'reject']);
        });
    });
});
