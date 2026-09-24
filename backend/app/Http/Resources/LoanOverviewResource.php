<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Loan overview used by the admin disbursement area and the member loan pages.
 * Composes the loan record with the applicant, product, approval decision,
 * disbursement record, repayment schedule and derived obligation figures.
 */
class LoanOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $application = $this->relationLoaded('application') ? $this->application : null;
        $decision = $application !== null && $application->relationLoaded('decision') ? $application->decision : null;

        $obligations = null;
        $nextDue = null;
        if ($this->relationLoaded('installments')) {
            $obligations = $this->obligationSummary();
            foreach ($this->installments->sortBy('installment_number') as $installment) {
                $isNextDue = $obligations['next_due_installment_id'] !== null
                    && $installment->id === $obligations['next_due_installment_id'];
                if ($isNextDue) {
                    $nextDue = $installment;
                    break;
                }
            }
        }

        return [
            'id' => $this->id,
            'loan_application_id' => $this->loan_application_id,
            'loan_number' => $this->loan_number,
            'status' => $this->status,
            'principal_amount_minor' => (int) $this->principal_amount_minor,
            'interest_rate_basis_points' => $this->interest_rate_basis_points,
            'interest_method' => $this->interest_method,
            'repayment_months' => $this->repayment_months,
            'interest_amount_minor' => $this->interest_amount_minor,
            'total_payable_minor' => $this->total_payable_minor,
            'disbursed_amount_minor' => (int) ($this->disbursed_amount_minor ?? 0),
            'disbursed_at' => $this->disbursed_at?->toISOString(),
            'start_date' => $this->start_date?->toISOString(),
            'maturity_date' => $this->maturity_date?->toISOString(),
            'member' => $this->whenLoaded('member', fn (): array => [
                'id' => $this->member->id,
                'member_number' => $this->member->member_number,
                'name' => $this->member->user?->name,
                'status' => $this->member->status,
                'membership_type' => $this->member->membership_type,
            ]),
            'product' => $application !== null && $application->relationLoaded('product') ? [
                'id' => $application->product->id,
                'name' => $application->product->name,
            ] : null,
            'decision' => $decision !== null ? [
                'decision' => $decision->decision,
                'approved_amount_minor' => $decision->approved_amount_minor,
                'interest_rate_basis_points' => $decision->interest_rate_basis_points,
                'interest_method' => $decision->interest_method,
                'repayment_months' => $decision->repayment_months,
                'decision_reason' => $decision->decision_reason,
                'decided_by' => $decision->decided_by,
                'decided_by_name' => $decision->relationLoaded('decidedBy') ? $decision->decidedBy?->name : null,
                'decided_at' => $decision->decided_at?->toISOString(),
            ] : null,
            'disbursement' => $this->whenLoaded('disbursement',
                fn () => $this->disbursement ? LoanDisbursementResource::make($this->disbursement) : null),
            'installments' => $this->whenLoaded('installments',
                fn () => LoanInstallmentResource::collection($this->installments)),
            'obligations' => $obligations !== null ? [
                'total_principal_minor' => $obligations['total_principal_minor'],
                'total_interest_minor' => $obligations['total_interest_minor'],
                'total_obligation_minor' => $obligations['total_obligation_minor'],
                'principal_paid_minor' => $obligations['principal_paid_minor'],
                'interest_paid_minor' => $obligations['interest_paid_minor'],
                'total_paid_minor' => $obligations['total_paid_minor'],
                'principal_outstanding_minor' => $obligations['principal_outstanding_minor'],
                'interest_outstanding_minor' => $obligations['interest_outstanding_minor'],
                'total_outstanding_minor' => $obligations['total_outstanding_minor'],
                'next_due_installment' => $nextDue !== null ? LoanInstallmentResource::make($nextDue) : null,
            ] : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
