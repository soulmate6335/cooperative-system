<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommitteeMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('committee_meetings.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'meeting_date' => ['sometimes', 'date'],
            'meeting_type' => ['sometimes', 'string', 'max:100'],
            'cutoff_days' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', 'in:scheduled,held,cancelled'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
