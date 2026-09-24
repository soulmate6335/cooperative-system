<?php

use App\Http\Controllers\AdminLoanController;
use App\Http\Controllers\AdminLoanDecisionController;
use App\Http\Controllers\AdminLoanDisbursementController;
use App\Http\Controllers\AdminLoanEligibilityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommitteeLoanController;
use App\Http\Controllers\CommitteeMeetingController;
use App\Http\Controllers\FinancialController;
use App\Http\Controllers\LoanApplicationController;
use App\Http\Controllers\LoanGuarantorController;
use App\Http\Controllers\LoanProductController;
use App\Http\Controllers\MemberApplicationController;
use App\Http\Controllers\MemberLoanController;
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

        // Loan products and committee meetings (admin/manageable).
        Route::get('loan-products', [LoanProductController::class, 'index']);
        Route::get('admin/loan-products', [LoanProductController::class, 'adminIndex']);
        Route::post('admin/loan-products', [LoanProductController::class, 'store']);
        Route::patch('admin/loan-products/{product}', [LoanProductController::class, 'update']);
        Route::post('admin/loan-products/{product}/deactivate', [LoanProductController::class, 'deactivate']);
        Route::post('admin/loan-products/{product}/activate', [LoanProductController::class, 'activate']);
        Route::get('admin/committee-meetings', [CommitteeMeetingController::class, 'index']);
        Route::post('admin/committee-meetings', [CommitteeMeetingController::class, 'store']);
        Route::patch('admin/committee-meetings/{meeting}', [CommitteeMeetingController::class, 'update']);

        // Member loan applications.
        Route::get('member/loans/eligibility', [LoanApplicationController::class, 'eligibility']);
        Route::post('member/loans/applications', [LoanApplicationController::class, 'store'])->middleware('throttle:loan_submission');
        Route::get('member/loans/applications', [LoanApplicationController::class, 'index']);
        Route::get('member/loans/applications/{application}', [LoanApplicationController::class, 'show']);
        Route::patch('member/loans/applications/{application}', [LoanApplicationController::class, 'update']);
        Route::post('member/loans/applications/{application}/submit', [LoanApplicationController::class, 'submit'])->middleware('throttle:loan_submission');
        Route::post('member/loans/applications/{application}/cancel', [LoanApplicationController::class, 'cancel']);
        Route::post('member/loans/applications/{application}/guarantors', [LoanGuarantorController::class, 'store']);
        Route::get('member/loans/applications/{application}/guarantor-candidates', [LoanGuarantorController::class, 'candidates']);
        Route::post('member/loans/applications/{application}/guarantors/{guarantor}/cancel', [LoanGuarantorController::class, 'cancel']);

        // Guarantor self-service.
        Route::get('member/guarantor-requests', [LoanGuarantorController::class, 'requests']);
        Route::post('member/guarantor-requests/{guarantor}/accept', [LoanGuarantorController::class, 'accept']);
        Route::post('member/guarantor-requests/{guarantor}/decline', [LoanGuarantorController::class, 'decline']);

        // Committee investigation.
        Route::get('committee/loan-applications', [CommitteeLoanController::class, 'index']);
        Route::get('committee/loan-applications/{application}', [CommitteeLoanController::class, 'show']);
        Route::post('committee/loan-applications/{application}/investigation/start', [CommitteeLoanController::class, 'startInvestigation']);
        Route::post('committee/loan-applications/{application}/investigation', [CommitteeLoanController::class, 'updateInvestigation']);
        Route::post('committee/loan-applications/{application}/investigation/submit', [CommitteeLoanController::class, 'submitInvestigation']);

        // Admin loan administration.
        Route::get('admin/loans/applications', [AdminLoanController::class, 'index']);
        Route::get('admin/loans/applications/{application}', [AdminLoanController::class, 'show']);
        Route::post('admin/loans/applications/{application}/assign', [AdminLoanController::class, 'assign']);
        Route::post('admin/loans/applications/{application}/cancel', [AdminLoanController::class, 'cancel']);
        Route::post('admin/loans/applications/{application}/approve', [AdminLoanController::class, 'approve']);
        Route::post('admin/loans/applications/{application}/reject', [AdminLoanController::class, 'reject']);

        // Admin final loan decisions.
        Route::get('admin/loan-decisions', [AdminLoanDecisionController::class, 'index']);
        Route::get('admin/loan-decisions/{application}', [AdminLoanDecisionController::class, 'show']);
        Route::post('admin/loan-decisions/{application}/approve', [AdminLoanDecisionController::class, 'approve']);
        Route::post('admin/loan-decisions/{application}/reject', [AdminLoanDecisionController::class, 'reject']);

        // Admin loan eligibility review.
        Route::get('admin/loan-eligibility/members', [AdminLoanEligibilityController::class, 'members']);
        Route::get('admin/loan-eligibility/assessments', [AdminLoanEligibilityController::class, 'assessment']);
        Route::post('admin/loan-eligibility/decisions', [AdminLoanEligibilityController::class, 'decide']);

        // Member loans (disbursed + active + completed) and repayment submission.
        Route::get('member/loans', [MemberLoanController::class, 'index']);
        Route::get('member/loans/{loan}', [MemberLoanController::class, 'show']);
        Route::get('member/loans/{loan}/schedule', [MemberLoanController::class, 'schedule']);
        Route::post('member/loans/{loan}/repayments', [MemberLoanController::class, 'repayment'])->middleware('throttle:loan_submission');

        // Admin/finance loan disbursement.
        Route::get('admin/loans/disbursement-queue', [AdminLoanDisbursementController::class, 'index']);
        Route::get('admin/loans/{loan}/disbursement', [AdminLoanDisbursementController::class, 'show']);
        Route::post('admin/loans/{loan}/disburse', [AdminLoanDisbursementController::class, 'disburse']);
    });
});
