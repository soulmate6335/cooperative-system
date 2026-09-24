<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublishNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('notices.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            // Optional scheduled publication time; defaults to now when omitted.
            'publish_at' => ['nullable', 'date'],
        ];
    }
}
