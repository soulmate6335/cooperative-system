<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('loans.apply') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'loan_product_id' => ['required', 'uuid', 'exists:loan_products,id'],
            'amount_requested_minor' => ['required', 'integer', 'min:1'],
            'purpose' => ['required', 'string', 'max:2000'],
        ];
    }
}
