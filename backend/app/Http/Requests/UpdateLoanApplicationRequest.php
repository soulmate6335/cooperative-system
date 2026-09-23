<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoanApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('application')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'loan_product_id' => ['sometimes', 'required', 'uuid', 'exists:loan_products,id'],
            'amount_requested_minor' => ['sometimes', 'required', 'integer', 'min:1'],
            'purpose' => ['sometimes', 'required', 'string', 'max:2000'],
        ];
    }
}
