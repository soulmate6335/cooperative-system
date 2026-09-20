<?php

namespace App\Http\Requests;

use App\Models\LoanInvestigation;
use Illuminate\Foundation\Http\FormRequest;

class AssignInvestigationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assign', LoanInvestigation::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'uuid', 'exists:users,id'],
        ];
    }
}
