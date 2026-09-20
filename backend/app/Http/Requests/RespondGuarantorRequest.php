<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RespondGuarantorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('respond', $this->route('guarantor')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'response_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
