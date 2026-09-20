<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommitteeMeetingRequest extends FormRequest
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
            'meeting_date' => ['required', 'date'],
            'meeting_type' => ['nullable', 'string', 'max:100'],
            'cutoff_days' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
