<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'hero_title' => ['nullable', 'string', 'max:200'],
            'hero_description' => ['nullable', 'string', 'max:2000'],
            'introduction' => ['nullable', 'string', 'max:5000'],
            'hero_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ];
    }
}
