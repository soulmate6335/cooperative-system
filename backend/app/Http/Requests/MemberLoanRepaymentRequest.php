<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MemberLoanRepaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->member !== null;
    }

    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'min:1'],
            'payment_method_id' => ['required', 'uuid', 'exists:payment_methods,id'],
            'payment_date' => ['required', 'date'],
            'reference_number' => ['required', 'string', 'max:100', 'unique:payments,reference_number'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
