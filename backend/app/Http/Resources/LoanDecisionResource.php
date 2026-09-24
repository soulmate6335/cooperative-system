<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanDecisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_application_id' => $this->loan_application_id,
            'decided_by' => $this->decided_by,
            'decided_by_name' => $this->whenLoaded('decidedBy', fn () => $this->decidedBy?->name ?? null),
            'decision' => $this->decision,
            'approved_amount_minor' => $this->approved_amount_minor,
            'interest_rate_basis_points' => $this->interest_rate_basis_points,
            'interest_method' => $this->interest_method,
            'repayment_months' => $this->repayment_months,
            'decision_reason' => $this->decision_reason,
            'decided_at' => $this->decided_at?->toISOString(),
            'created_at' => $this->created_at,
        ];
    }
}
