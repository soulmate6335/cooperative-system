<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Public executive shape. Photos are exposed as storage URLs only; internal
 * filesystem paths, authorship and visibility flags are never serialized.
 */
class ExecutiveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'position' => $this->position,
            'biography' => $this->biography,
            'photo_url' => $this->photo_path !== null ? Storage::disk('public')->url($this->photo_path) : null,
            'display_order' => $this->display_order,
        ];
    }
}
