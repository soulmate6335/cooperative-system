<?php

namespace App\Http\Resources;

use App\Models\LoanRepaymentAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanInstallmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $allocations = $this->relationLoaded('allocations')
            ? $this->allocations->where('status', LoanRepaymentAllocation::STATUS_POSTED)
            : $this->allocations()->where('status', LoanRepaymentAllocation::STATUS_POSTED)->get();

        $paidPrincipal = (int) $allocations->sum('principal_allocated_minor');
        $paidInterest = (int) $allocations->sum('interest_allocated_minor');
        $totalPaid = $paidPrincipal + $paidInterest;

        return [
            'id' => $this->id,
            'loan_id' => $this->loan_id,
            'installment_number' => (int) $this->installment_number,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'principal_due_minor' => (int) $this->principal_due_minor,
            'interest_due_minor' => (int) $this->interest_due_minor,
            'total_due_minor' => (int) $this->total_due_minor,
            'principal_paid_minor' => $paidPrincipal,
            'interest_paid_minor' => $paidInterest,
            'total_paid_minor' => $totalPaid,
            'outstanding_minor' => (int) $this->total_due_minor - $totalPaid,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toISOString(),
        ];
    }
}
