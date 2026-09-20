<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payments.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'uuid', 'exists:members,id'],
            'payment_method_id' => ['required', 'uuid', 'exists:payment_methods,id'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'payment_date' => ['required', 'date'],
            'reference_number' => ['required', 'string', 'max:100', 'unique:payments,reference_number'],
            'purpose' => ['required', 'string', 'in:contribution,savings,shares'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (in_array($this->input('purpose'), ['contribution', 'savings'], true)
                && (int) $this->input('amount_minor') < 10000) {
                $validator->errors()->add('amount_minor', 'Contributions and savings must be at least 10000 minor units.');
            }
        }];
    }
}
