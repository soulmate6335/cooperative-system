<?php

namespace App\Http\Requests;

use App\Models\LoanEligibilityDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideLoanEligibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('decide', LoanEligibilityDecision::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'uuid', 'exists:members,id'],
            'loan_product_id' => ['required', 'uuid', 'exists:loan_products,id'],
            'status' => ['required', Rule::in([LoanEligibilityDecision::ELIGIBLE, LoanEligibilityDecision::INELIGIBLE])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}