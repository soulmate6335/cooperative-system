<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'payment_id' => $this->payment_id,
            'reverses_transaction_id' => $this->reverses_transaction_id,
            'transaction_type' => $this->transaction_type,
            'direction' => $this->direction,
            'amount_minor' => (int) $this->amount_minor,
            'transaction_date' => $this->transaction_date,
            'reference' => $this->reference,
            'description' => $this->description,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'posted_by' => $this->posted_by,
            'posted_at' => $this->posted_at,
        ];
    }
}
