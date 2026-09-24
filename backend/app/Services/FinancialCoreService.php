<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialCoreService
{
    public function createAccount(Member $member, string $accountType): FinancialAccount
    {
        if ($member->status !== 'active') {
            throw ValidationException::withMessages(['member' => 'Only active members may have financial accounts.']);
        }

        if (! in_array($accountType, ['contribution', 'savings', 'shares', 'loan'], true)) {
            throw ValidationException::withMessages(['account_type' => 'The account type is not supported.']);
        }

        return FinancialAccount::create([
            'member_id' => $member->id,
            'account_type' => $accountType,
            'account_number' => $this->uniqueAccountNumber($accountType),
            'status' => 'active',
        ]);
    }

    public function recordPayment(array $data, User $recorder): Payment
    {
        return DB::transaction(function () use ($data, $recorder): Payment {
            $member = Member::query()->whereKey($data['member_id'])->where('status', 'active')->first();
            if (! $member) {
                throw ValidationException::withMessages(['member_id' => 'The member must be active.']);
            }

            $method = PaymentMethod::query()->whereKey($data['payment_method_id'])->where('is_active', true)->first();
            if (! $method) {
                throw ValidationException::withMessages(['payment_method_id' => 'The payment method is inactive or does not exist.']);
            }

            if (! in_array($data['purpose'], ['contribution', 'savings', 'shares', 'loan_repayment'], true)) {
                throw ValidationException::withMessages(['purpose' => 'The payment purpose is not supported.']);
            }

            if ($data['purpose'] === 'loan_repayment' && empty($data['loan_id'])) {
                throw ValidationException::withMessages(['loan_id' => 'A loan is required for a loan repayment payment.']);
            }

            if (in_array($data['purpose'], ['contribution', 'savings'], true) && (int) $data['amount_minor'] < 10000) {
                throw ValidationException::withMessages(['amount_minor' => 'Contributions and savings must be at least 10000 minor units.']);
            }

            return Payment::create([
                ...$data,
                'amount_minor' => (int) $data['amount_minor'],
                'status' => 'pending',
                'recorded_by' => $recorder->id,
            ]);
        });
    }

    public function verifyPayment(Payment $payment, User $verifier): FinancialTransaction
    {
        return DB::transaction(function () use ($payment, $verifier): FinancialTransaction {
            $payment = Payment::query()->lockForUpdate()->find($payment->id);
            if (! $payment) {
                throw (new ModelNotFoundException)->setModel(Payment::class, [$payment->id]);
            }

            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'Only pending payments can be verified.']);
            }

            if ($payment->recorded_by === $verifier->id) {
                throw ValidationException::withMessages(['verifier' => 'The verifier must be different from the recording officer.']);
            }

            // Loan repayments post to the member's "loan" ledger account rather
            // than a purpose-named account (there is no contribution/savings/
            // shares equivalent for a repayment purpose).
            $accountType = $payment->purpose === 'loan_repayment' ? 'loan' : $payment->purpose;

            $account = FinancialAccount::query()
                ->where('member_id', $payment->member_id)
                ->where('account_type', $accountType)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $account) {
                throw ValidationException::withMessages(['account' => 'An active account for this payment purpose is required.']);
            }

            $payment->update([
                'status' => 'verified',
                'verified_by' => $verifier->id,
                'verified_at' => now(),
            ]);

            return FinancialTransaction::create([
                'account_id' => $account->id,
                'payment_id' => $payment->id,
                'transaction_type' => $payment->purpose,
                'direction' => 'credit',
                'amount_minor' => $payment->amount_minor,
                'transaction_date' => $payment->payment_date,
                'reference' => $payment->reference_number,
                'description' => $payment->notes,
                'status' => 'posted',
                'created_by' => $payment->recorded_by,
                'posted_by' => $verifier->id,
                'posted_at' => now(),
            ]);
        });
    }

    /**
     * Post a loan disbursement to the Financial Core ledger: money leaving the
     * cooperative is a posted debit on the member's loan account. This is the
     * only disbursement posting path - loan services must not insert posted
     * transactions directly.
     */
    public function recordLoanDisbursement(
        FinancialAccount $account,
        int $amountMinor,
        string $reference,
        ?string $description,
        User $authorizer,
    ): FinancialTransaction {
        return DB::transaction(function () use ($account, $amountMinor, $reference, $description, $authorizer): FinancialTransaction {
            $account = FinancialAccount::query()->lockForUpdate()->find($account->id);

            if (! $account || $account->status !== 'active') {
                throw ValidationException::withMessages(['account' => 'An active financial account is required for disbursement.']);
            }

            return FinancialTransaction::create([
                'account_id' => $account->id,
                'transaction_type' => 'loan_disbursement',
                'direction' => 'debit',
                'amount_minor' => $amountMinor,
                'transaction_date' => now(),
                'reference' => $reference,
                'description' => $description ?? 'Loan disbursement',
                'status' => 'posted',
                'created_by' => $authorizer->id,
                'posted_by' => $authorizer->id,
                'posted_at' => now(),
            ]);
        });
    }

    public function issueReceipt(Payment $payment, User $issuer): Receipt
    {
        return DB::transaction(function () use ($payment, $issuer): Receipt {
            $paymentId = $payment->id;
            $payment = Payment::query()->lockForUpdate()->find($paymentId);
            if (! $payment) {
                throw (new ModelNotFoundException)->setModel(Payment::class, [$paymentId]);
            }

            if ($payment->status !== 'verified') {
                throw ValidationException::withMessages(['payment' => 'Only verified payments may receive receipts.']);
            }

            if ($payment->receipt()->exists()) {
                throw ValidationException::withMessages(['payment' => 'A receipt has already been issued for this payment.']);
            }

            return Receipt::create([
                'payment_id' => $payment->id,
                'receipt_number' => $this->uniqueReceiptNumber(),
                'issued_at' => now(),
                'issued_by' => $issuer->id,
            ]);
        });
    }

    public function balance(FinancialAccount $account): int
    {
        return (int) $account->transactions()->posted()->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE -amount_minor END), 0) AS balance")->value('balance');
    }

    public function reverse(FinancialTransaction $transaction, User $creator): FinancialTransaction
    {
        return DB::transaction(function () use ($transaction, $creator): FinancialTransaction {
            $transaction = FinancialTransaction::query()->lockForUpdate()->find($transaction->id);
            if (! $transaction) {
                throw (new ModelNotFoundException)->setModel(FinancialTransaction::class, [$transaction->id]);
            }

            if ($transaction->status !== 'posted') {
                throw ValidationException::withMessages(['transaction' => 'Only posted transactions can be reversed.']);
            }

            if ($transaction->reversal()->exists()) {
                throw ValidationException::withMessages(['transaction' => 'This transaction has already been reversed.']);
            }

            return FinancialTransaction::create([
                'account_id' => $transaction->account_id,
                'reverses_transaction_id' => $transaction->id,
                'transaction_type' => 'reversal',
                'direction' => $transaction->direction === 'credit' ? 'debit' : 'credit',
                'amount_minor' => $transaction->amount_minor,
                'transaction_date' => now(),
                'reference' => 'REV-'.strtoupper(Str::random(12)),
                'description' => 'Reversal of '.$transaction->reference,
                'status' => 'posted',
                'created_by' => $creator->id,
                'posted_by' => $creator->id,
                'posted_at' => now(),
            ]);
        });
    }

    private function uniqueAccountNumber(string $type): string
    {
        do {
            $number = strtoupper(substr($type, 0, 3)).'-'.strtoupper(Str::random(10));
        } while (FinancialAccount::where('account_number', $number)->exists());

        return $number;
    }

    private function uniqueReceiptNumber(): string
    {
        do {
            $number = 'RCT-'.strtoupper(Str::random(12));
        } while (Receipt::where('receipt_number', $number)->exists());

        return $number;
    }
}
