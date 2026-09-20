<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLoanProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:loan_products,name'],
            'description' => ['nullable', 'string'],
            'minimum_membership_months' => ['nullable', 'integer', 'min:0'],
            'minimum_amount_minor' => ['nullable', 'integer', 'min:0'],
            'maximum_amount_minor' => ['nullable', 'integer', 'min:0'],
            'interest_rate_basis_points' => ['nullable', 'integer', 'min:0'],
            'interest_method' => ['nullable', 'string', 'in:flat,reducing_balance'],
            'repayment_months' => ['nullable', 'integer', 'min:1'],
            'required_guarantors' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['minimum_amount_minor', 'maximum_amount_minor'])) {
                    return;
                }

                $minimum = (int) ($this->input('minimum_amount_minor', 0));
                $maximum = $this->input('maximum_amount_minor');

                if ($maximum !== null && (int) $maximum < $minimum) {
                    $validator->errors()->add('maximum_amount_minor', 'The maximum amount must be greater than or equal to the minimum.');
                }
            },
        ];
    }
}
