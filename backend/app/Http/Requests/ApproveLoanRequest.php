<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approve', $this->route('application')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'approved_amount_minor' => ['required', 'integer', 'min:1'],
            'interest_rate_basis_points' => ['required', 'integer', 'min:0'],
            'interest_method' => ['required', 'string', 'in:flat,reducing_balance'],
            'repayment_months' => ['required', 'integer', 'min:1'],
            'decision_reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
