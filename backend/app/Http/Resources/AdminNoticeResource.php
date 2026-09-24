<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Admin notice shape: extends the public-safe shape with lifecycle status,
 * authorship and internal timestamps. Only reachable behind notices.manage.
 */
class AdminNoticeResource extends NoticeResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'status' => $this->status,
            'created_by' => $this->author?->name,
            'updated_by' => $this->updater?->name,
            'updated_at' => $this->updated_at?->toISOString(),
        ]);
    }
}
