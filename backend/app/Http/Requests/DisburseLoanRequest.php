<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DisburseLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('loans.disburse') ?? false;
    }

    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:100'],
            'payment_method_id' => ['nullable', 'uuid', 'exists:payment_methods,id'],
        ];
    }
}
