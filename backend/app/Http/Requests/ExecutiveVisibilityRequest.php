<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecutiveVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('executives.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'is_visible' => ['required', 'boolean'],
        ];
    }
}
