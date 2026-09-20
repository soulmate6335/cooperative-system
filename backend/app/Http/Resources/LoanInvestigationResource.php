<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanInvestigationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_application_id' => $this->loan_application_id,
            'assigned_to' => $this->assigned_to,
            'investigation_date' => $this->investigation_date?->toISOString(),
            'member_findings' => $this->member_findings,
            'savings_findings' => $this->savings_findings,
            'shares_findings' => $this->shares_findings,
            'existing_loan_findings' => $this->existing_loan_findings,
            'guarantor_findings' => $this->guarantor_findings,
            'committee_comments' => $this->committee_comments,
            'recommendation' => $this->recommendation,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
