<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_application_id' => $this->loan_application_id,
            'member_id' => $this->member_id,
            'loan_number' => $this->loan_number,
            'principal_amount_minor' => $this->principal_amount_minor,
            'interest_rate_basis_points' => $this->interest_rate_basis_points,
            'interest_method' => $this->interest_method,
            'repayment_months' => $this->repayment_months,
            'interest_amount_minor' => $this->interest_amount_minor,
            'total_payable_minor' => $this->total_payable_minor,
            'disbursed_amount_minor' => $this->disbursed_amount_minor,
            'disbursed_at' => $this->disbursed_at?->toISOString(),
            'start_date' => $this->start_date?->toISOString(),
            'maturity_date' => $this->maturity_date?->toISOString(),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
