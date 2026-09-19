<?php

namespace App\Http\Requests;

use App\Models\MemberApplication;
use Illuminate\Foundation\Http\FormRequest;

class ListMemberApplicationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', MemberApplication::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:pending,under_review,approved,rejected'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
