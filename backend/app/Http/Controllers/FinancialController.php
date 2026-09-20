<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateFinancialAccountRequest;
use App\Http\Requests\StorePaymentMethodRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\FinancialAccountResource;
use App\Http\Resources\FinancialTransactionResource;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\PaymentResource;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\FinancialCoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FinancialController extends Controller
{
    public function __construct(private readonly FinancialCoreService $service) {}

    public function paymentMethods(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Payment methods retrieved successfully.',
            'data' => PaymentMethodResource::collection(PaymentMethod::query()->where('is_active', true)->orderBy('display_order')->get()),
        ]);
    }

    public function storePaymentMethod(StorePaymentMethodRequest $request): JsonResponse
    {
        $method = PaymentMethod::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment method created successfully.',
            'data' => PaymentMethodResource::make($method),
        ], 201);
    }

    public function storeAccount(CreateFinancialAccountRequest $request): JsonResponse
    {
        $account = $this->service->createAccount(Member::findOrFail($request->validated('member_id')), $request->validated('account_type'));

        return response()->json([
            'success' => true,
            'message' => 'Financial account created successfully.',
            'data' => FinancialAccountResource::make($account),
        ], 201);
    }

    public function ownAccounts(Request $request): JsonResponse
    {
        $member = $request->user()->member;
        if (! $member || $member->status !== 'active') {
            throw ValidationException::withMessages(['member' => 'An active membership is required.']);
        }

        $accounts = FinancialAccount::query()->where('member_id', $member->id)->where('status', 'active')->get();
        $accounts->each(fn (FinancialAccount $account) => $account->balance_minor = $this->service->balance($account));

        return response()->json([
            'success' => true,
            'message' => 'Financial accounts retrieved successfully.',
            'data' => FinancialAccountResource::collection($accounts),
        ]);
    }

    public function storePayment(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->service->recordPayment($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded and awaiting verification.',
            'data' => PaymentResource::make($payment),
        ], 201);
    }

    public function verifyPayment(Request $request, Payment $payment): JsonResponse
    {
        if (! $request->user()->hasPermission('payments.verify')) {
            abort(403);
        }

        $transaction = $this->service->verifyPayment($payment, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Payment verified and posted successfully.',
            'data' => FinancialTransactionResource::make($transaction),
        ]);
    }

    public function issueReceipt(Request $request, Payment $payment): JsonResponse
    {
        if (! $request->user()->hasPermission('receipts.create')) {
            abort(403);
        }

        $receipt = $this->service->issueReceipt($payment, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Receipt issued successfully.',
            'data' => ['id' => $receipt->id, 'receipt_number' => $receipt->receipt_number, 'payment_id' => $receipt->payment_id, 'issued_at' => $receipt->issued_at],
        ], 201);
    }

    public function accountTransactions(Request $request, FinancialAccount $account): JsonResponse
    {
        if ($account->member->user_id !== $request->user()->id && ! $request->user()->hasPermission('transactions.view')) {
            abort(403);
        }

        $transactions = $account->transactions()->latest('transaction_date')->paginate(min($request->integer('per_page', 20), 100));

        return response()->json([
            'success' => true,
            'message' => 'Account transactions retrieved successfully.',
            'data' => FinancialTransactionResource::collection($transactions),
        ]);
    }

    public function reverseTransaction(Request $request, FinancialTransaction $transaction): JsonResponse
    {
        if (! $request->user()->hasPermission('transactions.reverse')) {
            abort(403);
        }

        $reversal = $this->service->reverse($transaction, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Financial transaction reversed successfully.',
            'data' => FinancialTransactionResource::make($reversal),
        ]);
    }
}
