<?php

namespace App\Services;

use App\Models\OrganizationContent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Homepage organization content. One active record is used at a time (guarded
 * by a partial unique index) so no two conflicting homepage configurations can
 * coexist; all fields are optional so the public homepage can fall back to
 * static copy when the organization has not configured content yet.
 */
class OrganizationContentService
{
    public function current(): ?OrganizationContent
    {
        return OrganizationContent::current();
    }

    public function update(array $data, User $admin): OrganizationContent
    {
        return DB::transaction(function () use ($data): OrganizationContent {
            $content = OrganizationContent::query()
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($content === null) {
                // Locked single-active invariant: creating here is safe because
                // the partial unique index rejects any second active row.
                $content = OrganizationContent::create(['is_active' => true]);
            }

            if (isset($data['hero_image']) && $data['hero_image'] instanceof UploadedFile) {
                $previousImage = $content->hero_image_path;
                $data['hero_image_path'] = $data['hero_image']->store('homepage', 'public');
                unset($data['hero_image']);

                if ($previousImage !== null && $previousImage !== $data['hero_image_path']) {
                    Storage::disk('public')->delete($previousImage);
                }
            } else {
                unset($data['hero_image']);
            }

            $content->update($data);

            return $content->fresh();
        });
    }
}
