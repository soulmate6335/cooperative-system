<?php

namespace App\Services;

use App\Models\FinancialTransaction;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanRepaymentAllocation;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentVerified;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Loan repayment workflow built on the existing Financial Core.
 *
 * A repayment is a Payment (purpose = loan_repayment) recorded in the core as
 * pending. Verification posts the Financial Core credit transaction to the
 * member's loan ledger account and, in the same transaction, allocates the
 * posted amount to installments. Allocations are only ever created from
 * POSTED transactions - never from pending payments. Reversals route through
 * the core's existing reversal architecture and void the affected allocations
 * rather than editing or deleting history.
 */
class LoanRepaymentService
{
    public function __construct(private readonly FinancialCoreService $financialCore) {}

    public function submit(array $data, User $recorder): Payment
    {
        return DB::transaction(function () use ($data, $recorder): Payment {
            $loan = Loan::query()->lockForUpdate()->findOrFail($data['loan_id']);

            if ($loan->member_id !== $data['member_id']) {
                throw ValidationException::withMessages(['loan_id' => 'The loan does not belong to the stated member.']);
            }

            if ($loan->status !== Loan::STATUS_DISBURSED) {
                throw ValidationException::withMessages(['loan' => 'Repayments can only be recorded against a disbursed loan.']);
            }

            if ($loan->member->status !== 'active') {
                throw ValidationException::withMessages(['member' => 'The member must be active to record a repayment.']);
            }

            $amount = (int) $data['amount_minor'];
            $outstanding = $loan->obligationSummary()['total_outstanding_minor'];

            if ($outstanding < 1) {
                throw ValidationException::withMessages(['amount_minor' => 'This loan has no outstanding obligation.']);
            }

            // Overpayment is rejected rather than credited against future
            // obligations: no advance-payment or unapplied-credit rule exists.
            if ($amount > $outstanding) {
                throw ValidationException::withMessages([
                    'amount_minor' => 'The repayment amount exceeds the outstanding loan obligation.',
                ]);
            }

            return $this->financialCore->recordPayment([
                ...$data,
                'purpose' => 'loan_repayment',
            ], $recorder);
        });
    }

    public function verify(Payment $payment, User $verifier): FinancialTransaction
    {
        return DB::transaction(function () use ($payment, $verifier): FinancialTransaction {
            // The core posts the credit transaction and flips the payment to
            // verified. If allocation then fails (e.g. the outstanding balance
            // shrank while the payment sat pending), the nested transaction
            // rolls back and nothing is posted.
            $transaction = $this->financialCore->verifyPayment($payment, $verifier);
            $this->allocate($payment, $transaction);

            // Repayment verification status notification for the applicant member.
            $payment->member->user->notify(new PaymentVerified(
                (string) $payment->purpose,
                (int) $payment->amount_minor,
                (string) $payment->reference_number,
                (string) $payment->id,
                $payment->loan?->loan_number,
            ));

            return $transaction;
        });
    }

    public function reverse(FinancialTransaction $transaction, User $creator): FinancialTransaction
    {
        return DB::transaction(function () use ($transaction, $creator): FinancialTransaction {
            // Reuse the Financial Core reversal architecture verbatim: the
            // original posted row is never edited; an offset reversal row is
            // created and its repayment allocations are voided atomically.
            $reversal = $this->financialCore->reverse($transaction, $creator);

            if ($transaction->transaction_type === 'loan_repayment') {
                $this->unapply($transaction, $reversal);
            }

            return $reversal;
        });
    }

    /**
     * Deterministic allocation strategy: oldest unpaid installment first,
     * interest due before principal within an installment, and never into
     * future installments while an earlier one is unpaid.
     */
    private function allocate(Payment $payment, FinancialTransaction $transaction): void
    {
        if (! $payment->loan_id) {
            throw ValidationException::withMessages(['loan_id' => 'A loan is required for repayment allocation.']);
        }

        $loan = Loan::query()->lockForUpdate()->findOrFail($payment->loan_id);

        if ($loan->member_id !== $payment->member_id) {
            throw ValidationException::withMessages(['loan_id' => 'The repayment payment does not belong to the loan member.']);
        }

        $installments = $loan->installments()->orderBy('installment_number')->get();
        $posted = $loan->repaymentAllocations()
            ->where('status', LoanRepaymentAllocation::STATUS_POSTED)
            ->get()
            ->groupBy('installment_id');

        $remaining = (int) $payment->amount_minor;
        $rows = [];

        foreach ($installments as $installment) {
            if ($remaining < 1) {
                break;
            }

            $paid = $posted->get($installment->id, collect());
            $dueInterest = max(0, (int) $installment->interest_due_minor - (int) $paid->sum('interest_allocated_minor'));
            $duePrincipal = max(0, (int) $installment->principal_due_minor - (int) $paid->sum('principal_allocated_minor'));
            $due = $dueInterest + $duePrincipal;

            if ($due < 1) {
                continue;
            }

            $allocated = min($remaining, $due);
            $allocatedInterest = min($dueInterest, $allocated);
            $allocatedPrincipal = $allocated - $allocatedInterest;

            $rows[] = [
                'installment' => $installment,
                'amount' => $allocated,
                'interest' => $allocatedInterest,
                'principal' => $allocatedPrincipal,
            ];

            $remaining -= $allocated;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages(['amount_minor' => 'The repayment amount exceeds the outstanding loan obligation.']);
        }

        foreach ($rows as $row) {
            LoanRepaymentAllocation::create([
                'payment_id' => $payment->id,
                'financial_transaction_id' => $transaction->id,
                'loan_id' => $loan->id,
                'installment_id' => $row['installment']->id,
                'amount_minor' => $row['amount'],
                'principal_allocated_minor' => $row['principal'],
                'interest_allocated_minor' => $row['interest'],
                'status' => LoanRepaymentAllocation::STATUS_POSTED,
            ]);
        }

        $this->refreshInstallments($loan);
        $this->refreshLoanStatus($loan);
    }

    /** Void every allocation funded by a reversed repayment transaction. */
    private function unapply(FinancialTransaction $transaction, FinancialTransaction $reversal): void
    {
        $allocations = LoanRepaymentAllocation::query()
            ->where('financial_transaction_id', $transaction->id)
            ->where('status', LoanRepaymentAllocation::STATUS_POSTED)
            ->get();

        if ($allocations->isEmpty()) {
            return;
        }

        $loan = Loan::query()->lockForUpdate()->findOrFail($allocations->first()->loan_id);

        foreach ($allocations as $allocation) {
            $allocation->update([
                'status' => LoanRepaymentAllocation::STATUS_VOIDED,
                'voided_by_transaction_id' => $reversal->id,
            ]);
        }

        $this->refreshInstallments($loan);
        $this->refreshLoanStatus($loan);
    }

    /** Recompute paid counters from posted allocations (never from pending payments). */
    private function refreshInstallments(Loan $loan): void
    {
        $posted = $loan->repaymentAllocations()
            ->where('status', LoanRepaymentAllocation::STATUS_POSTED)
            ->get()
            ->groupBy('installment_id');

        foreach ($loan->installments()->get() as $installment) {
            $paid = $posted->get($installment->id, collect());
            $paidPrincipal = (int) $paid->sum('principal_allocated_minor');
            $paidInterest = (int) $paid->sum('interest_allocated_minor');
            $fullyPaid = $paidPrincipal >= (int) $installment->principal_due_minor
                && $paidInterest >= (int) $installment->interest_due_minor;

            $installment->update([
                'status' => $fullyPaid ? LoanInstallment::STATUS_PAID : LoanInstallment::STATUS_PENDING,
                'paid_at' => $fullyPaid ? ($installment->paid_at ?? now()) : null,
            ]);
        }
    }

    /** Completion is derived from posted allocations only - never from pending payments. */
    private function refreshLoanStatus(Loan $loan): void
    {
        $outstanding = $loan->obligationSummary()['total_outstanding_minor'];

        if ($loan->status === Loan::STATUS_DISBURSED && $outstanding <= 0) {
            $loan->update(['status' => Loan::STATUS_COMPLETED]);
        } elseif ($loan->status === Loan::STATUS_COMPLETED && $outstanding > 0) {
            // A reversal can reopen a completed loan.
            $loan->update(['status' => Loan::STATUS_DISBURSED]);
        }
    }
}
