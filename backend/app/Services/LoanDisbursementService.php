<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Authorizes and executes the single full disbursement of an approved loan.
 *
 * The disbursement is atomic: the disbursement record, the Financial Core
 * posted debit transaction, the generated repayment schedule, and the loan
 * status transition must all succeed together or none of them persist. The
 * loan row is locked first so two administrators cannot disburse simultaneously.
 */
class LoanDisbursementService
{
    public function __construct(
        private readonly FinancialCoreService $financialCore,
        private readonly LoanRepaymentScheduleService $schedules,
    ) {}

    /**
     * @return array{loan: Loan, disbursement: LoanDisbursement, transaction: FinancialTransaction}
     */
    public function disburse(Loan $loan, User $authorizer, array $payload): array
    {
        return DB::transaction(function () use ($loan, $authorizer, $payload): array {
            $loan = Loan::query()->lockForUpdate()->find($loan->id);
            if (! $loan) {
                throw (new ModelNotFoundException)->setModel(Loan::class, [$loan->id]);
            }

            if ($loan->status !== Loan::STATUS_PENDING_DISBURSEMENT) {
                throw ValidationException::withMessages(['loan' => 'Only approved loans awaiting disbursement can be disbursed.']);
            }

            if ($loan->member->status !== 'active') {
                throw ValidationException::withMessages(['member' => 'The loan cannot be disbursed while the member is not active.']);
            }

            $amount = (int) $payload['amount_minor'];
            if ($amount !== (int) $loan->principal_amount_minor) {
                throw ValidationException::withMessages(['amount_minor' => 'Disbursement must equal the full approved principal amount.']);
            }

            // The member's loan ledger account receives the outgoing debit.
            $account = $loan->member->financialAccounts()
                ->where('account_type', 'loan')
                ->where('status', 'active')
                ->first();

            if (! $account instanceof FinancialAccount) {
                $account = $this->financialCore->createAccount($loan->member, 'loan');
            }

            $disbursement = LoanDisbursement::create([
                'loan_id' => $loan->id,
                'amount_minor' => $amount,
                'payment_method_id' => $payload['payment_method_id'] ?? null,
                'financial_account_id' => $account->id,
                'reference' => $payload['reference'] ?? null,
                'status' => LoanDisbursement::STATUS_POSTED,
                'disbursed_at' => now(),
                'authorized_by' => $authorizer->id,
            ]);

            $transaction = $this->financialCore->recordLoanDisbursement(
                $account,
                $amount,
                $loan->loan_number,
                'Disbursement of '.$loan->loan_number,
                $authorizer,
            );

            // Generating the schedule may surface a genuinely undefined business
            // rule (reducing-balance): the exception rolls the whole disbursement
            // back so no orphan record or transaction is left behind.
            $installments = $this->schedules->generate($loan);

            $loan->update([
                'status' => Loan::STATUS_DISBURSED,
                'disbursed_amount_minor' => $amount,
                'disbursed_at' => now(),
                'start_date' => now(),
                'maturity_date' => $installments->last()->due_date,
            ]);

            return [
                'loan' => $loan->fresh(),
                'disbursement' => $disbursement,
                'transaction' => $transaction,
                'installments' => $installments,
            ];
        });
    }
}
