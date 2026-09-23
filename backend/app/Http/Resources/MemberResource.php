<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_number' => $this->member_number,
            'profile_photo' => $this->profile_photo,
            'joined_at' => $this->joined_at,
            'status' => $this->status,
            'membership_type' => $this->membership_type,
            'name' => $this->whenLoaded('user', fn () => $this->user?->name ?? null),
        ];
    }
}
