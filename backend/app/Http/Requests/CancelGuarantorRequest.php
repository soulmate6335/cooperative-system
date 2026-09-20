<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelGuarantorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancelRequest', $this->route('guarantor')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
