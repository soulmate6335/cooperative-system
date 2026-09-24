<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartInvestigationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('startInvestigation', $this->route('application')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
