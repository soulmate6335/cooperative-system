<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'payment_method_id' => $this->payment_method_id,
            'amount_minor' => (int) $this->amount_minor,
            'payment_date' => $this->payment_date,
            'reference_number' => $this->reference_number,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'recorded_by' => $this->recorded_by,
            'verified_by' => $this->verified_by,
            'verified_at' => $this->verified_at,
        ];
    }
}
