<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowLoanDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewLoanDecision', $this->route('application')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
