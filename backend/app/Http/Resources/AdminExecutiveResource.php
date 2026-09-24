<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Admin executive shape: adds the visibility flag and internal timestamps on
 * top of the public shape. Only reachable behind executives.manage.
 */
class AdminExecutiveResource extends ExecutiveResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'is_visible' => $this->is_visible,
            'created_by' => $this->author?->name,
            'updated_by' => $this->updater?->name,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ]);
    }
}
