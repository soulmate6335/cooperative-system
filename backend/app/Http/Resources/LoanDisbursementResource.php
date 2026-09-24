<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanDisbursementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_id' => $this->loan_id,
            'amount_minor' => (int) $this->amount_minor,
            'payment_method_id' => $this->payment_method_id,
            'financial_account_id' => $this->financial_account_id,
            'reference' => $this->reference,
            'status' => $this->status,
            'disbursed_at' => $this->disbursed_at?->toISOString(),
            'authorized_by' => $this->authorized_by,
        ];
    }
}
