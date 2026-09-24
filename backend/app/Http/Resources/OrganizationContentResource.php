<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OrganizationContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'hero_title' => $this->hero_title,
            'hero_description' => $this->hero_description,
            'hero_image_url' => $this->hero_image_path !== null ? Storage::disk('public')->url($this->hero_image_path) : null,
            'introduction' => $this->introduction,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
