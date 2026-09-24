<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInstallment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deterministic repayment schedule generation from the loan's SNAPSHOTTED
 * approved terms (never the current product configuration).
 *
 * Flat method: total interest = principal x rate, allocated across installments
 * with integer minor-unit rounding such that the installments sum back exactly
 * to the calculated totals - no money is created or lost through rounding.
 *
 * NOTE: no reducing-balance calculation convention exists anywhere in the
 * codebase (the enum value is only validated). Rather than inventing financial
 * rules, schedule generation refuses reducing-balance loans with a clear error
 * until the formula is formally defined.
 */
class LoanRepaymentScheduleService
{
    public function generate(Loan $loan): Collection
    {
        return DB::transaction(function () use ($loan): Collection {
            $loan = Loan::query()->lockForUpdate()->find($loan->id);
            if (! $loan) {
                throw (new ModelNotFoundException)->setModel(Loan::class, [$loan->id]);
            }

            if ($loan->interest_method !== 'flat') {
                throw ValidationException::withMessages([
                    'interest_method' => 'The reducing-balance repayment schedule formula is not defined in this system. Disbursement is unavailable for reducing-balance loans until the calculation convention is specified.',
                ]);
            }

            if ($loan->installments()->exists()) {
                throw ValidationException::withMessages(['loan' => 'A repayment schedule already exists for this loan.']);
            }

            $principal = (int) $loan->principal_amount_minor;
            $rateBasisPoints = (int) $loan->interest_rate_basis_points;
            $months = (int) $loan->repayment_months;

            // Integer math: total interest = principal x rate / 10000 (rate is
            // expressed in basis points). Flooring at the kobo level keeps the
            // calculation fully deterministic.
            $totalInterest = intdiv($principal * $rateBasisPoints, 10000);
            $totalPayable = $principal + $totalInterest;

            $start = $loan->start_date ? $loan->start_date->copy() : now();

            $installments = [];
            for ($index = 1; $index <= $months; $index++) {
                $principalDue = intdiv($principal, $months) + ($index - 1 < $principal % $months ? 1 : 0);
                $interestDue = intdiv($totalInterest, $months) + ($index - 1 < $totalInterest % $months ? 1 : 0);

                $installments[] = LoanInstallment::create([
                    'loan_id' => $loan->id,
                    'installment_number' => $index,
                    'due_date' => $start->copy()->addMonthsNoOverflow($index)->format('Y-m-d'),
                    'principal_due_minor' => $principalDue,
                    'interest_due_minor' => $interestDue,
                    'total_due_minor' => $principalDue + $interestDue,
                    'status' => LoanInstallment::STATUS_PENDING,
                ]);
            }

            $loan->update([
                'interest_amount_minor' => $totalInterest,
                'total_payable_minor' => $totalPayable,
            ]);

            return collect($installments);
        });
    }
}
