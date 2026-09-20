<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommitteeListLoanApplicationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('loans.investigate') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:guarantors_confirmed,under_investigation,committee_reviewed,pending_admin_decision'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
