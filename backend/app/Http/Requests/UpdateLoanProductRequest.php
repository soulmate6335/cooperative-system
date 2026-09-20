<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('loan_products.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('loan_products', 'name')->ignore($this->route('product'))],
            'description' => ['nullable', 'string'],
            'minimum_membership_months' => ['sometimes', 'integer', 'min:0'],
            'minimum_amount_minor' => ['sometimes', 'integer', 'min:0'],
            'maximum_amount_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'interest_rate_basis_points' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'interest_method' => ['sometimes', 'nullable', 'string', 'in:flat,reducing_balance'],
            'repayment_months' => ['sometimes', 'integer', 'min:1'],
            'required_guarantors' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
