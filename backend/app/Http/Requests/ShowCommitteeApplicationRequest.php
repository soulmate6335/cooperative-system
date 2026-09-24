<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowCommitteeApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('committeeView', $this->route('application')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
