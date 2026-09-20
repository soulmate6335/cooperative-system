<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'minimum_membership_months' => $this->minimum_membership_months,
            'minimum_amount_minor' => $this->minimum_amount_minor,
            'maximum_amount_minor' => $this->maximum_amount_minor,
            'interest_rate_basis_points' => $this->interest_rate_basis_points,
            'interest_method' => $this->interest_method,
            'repayment_months' => $this->repayment_months,
            'required_guarantors' => $this->required_guarantors,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
