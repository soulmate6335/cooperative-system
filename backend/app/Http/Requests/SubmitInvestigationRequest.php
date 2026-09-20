<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitInvestigationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('application')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'member_findings' => ['sometimes', 'nullable', 'string'],
            'savings_findings' => ['sometimes', 'nullable', 'string'],
            'shares_findings' => ['sometimes', 'nullable', 'string'],
            'existing_loan_findings' => ['sometimes', 'nullable', 'string'],
            'guarantor_findings' => ['sometimes', 'nullable', 'string'],
            'committee_comments' => ['sometimes', 'nullable', 'string'],
            'recommendation' => ['required', 'string', 'max:2000'],
        ];
    }
}
