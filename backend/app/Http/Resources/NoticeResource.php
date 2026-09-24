<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-safe notice shape used by the homepage, public notices API and the
 * member notices API. Only published/visible records ever reach this resource
 * (enforced by the query scopes and the detail guard in the controller).
 */
class NoticeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'visibility' => $this->visibility,
            'publish_at' => $this->publish_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
