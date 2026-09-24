<?php

namespace App\Services;

use App\Models\Executive;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Executive profile management including photo upload. Photos are stored on
 * the public disk with generated filenames; a replacement stores the new file
 * first and only then swaps the reference and removes the old file, so a
 * missing image can never break the profile record.
 */
class ExecutiveService
{
    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Executive::query()
            ->with('author')
            ->when(! empty($filters['search']), function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query->where('name', 'ilike', '%'.$filters['search'].'%')
                        ->orWhere('position', 'ilike', '%'.$filters['search'].'%');
                });
            })
            ->when(array_key_exists('is_visible', $filters) && $filters['is_visible'] !== null && $filters['is_visible'] !== '', fn ($query) => $query->where('is_visible', (bool) $filters['is_visible']))
            ->ordered()
            ->paginate($perPage);
    }

    public function create(array $data, User $admin): Executive
    {
        if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
            $data['photo_path'] = $this->storePhoto($data['photo']);
        }
        unset($data['photo']);

        return Executive::create([
            ...$data,
            'display_order' => (int) ($data['display_order'] ?? 0),
            'is_visible' => true,
            'created_by' => $admin->id,
        ]);
    }

    public function update(Executive $executive, array $data, User $admin): Executive
    {
        return DB::transaction(function () use ($executive, $data, $admin): Executive {
            $executive = Executive::query()->lockForUpdate()->findOrFail($executive->id);

            $previousPhoto = $executive->photo_path;

            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $data['photo_path'] = $this->storePhoto($data['photo']);
            }
            unset($data['photo']);

            $executive->update([...$data, 'updated_by' => $admin->id]);

            if ($previousPhoto !== null && isset($data['photo_path']) && $data['photo_path'] !== $previousPhoto) {
                Storage::disk('public')->delete($previousPhoto);
            }

            return $executive->fresh();
        });
    }

    public function setVisibility(Executive $executive, User $admin, bool $visible): Executive
    {
        $executive->update([
            'is_visible' => $visible,
            'updated_by' => $admin->id,
        ]);

        return $executive->fresh();
    }

    private function storePhoto(UploadedFile $photo): string
    {
        return $photo->store('executives', 'public');
    }
}
