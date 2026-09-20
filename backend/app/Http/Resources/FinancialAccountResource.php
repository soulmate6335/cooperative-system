<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'account_type' => $this->account_type,
            'account_number' => $this->account_number,
            'status' => $this->status,
            'balance_minor' => $this->when(isset($this->balance_minor), (int) $this->balance_minor),
        ];
    }
}
