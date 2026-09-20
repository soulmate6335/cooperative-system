<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanGuarantorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('request', $this->route('application')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'guarantor_member_id' => ['required', 'uuid', 'exists:members,id'],
        ];
    }
}
