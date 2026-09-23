<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListGuarantorCandidatesRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
