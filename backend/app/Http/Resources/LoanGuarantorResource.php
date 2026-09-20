<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanGuarantorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_application_id' => $this->loan_application_id,
            'guarantor_member' => MemberResource::make($this->whenLoaded('guarantorMember')),
            'status' => $this->status,
            'requested_at' => $this->requested_at?->toISOString(),
            'responded_at' => $this->responded_at?->toISOString(),
            'responded_by' => $this->responded_by,
            'response_note' => $this->response_note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
