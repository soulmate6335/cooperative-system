<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_number' => $this->application_number,
            'member' => MemberResource::make($this->whenLoaded('member')),
            'loan_product' => LoanProductResource::make($this->whenLoaded('product')),
            'committee_meeting' => CommitteeMeetingResource::make($this->whenLoaded('meeting')),
            'amount_requested_minor' => $this->amount_requested_minor,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'savings_balance_minor' => $this->savings_balance_minor,
            'shares_balance_minor' => $this->shares_balance_minor,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'guarantors' => LoanGuarantorResource::collection($this->whenLoaded('guarantors')),
            'investigation' => LoanInvestigationResource::make($this->whenLoaded('investigation')),
            'decision' => LoanDecisionResource::make($this->whenLoaded('decision')),
            'loan' => LoanResource::make($this->whenLoaded('loan')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
