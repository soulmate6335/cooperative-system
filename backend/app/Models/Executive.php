<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Executive profile shown on the public homepage. Photos are stored through
 * the application's storage abstraction (public disk) with generated
 * filenames; the model only ever exposes a URL, never a filesystem path.
 */
class Executive extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name', 'position', 'biography', 'photo_path',
        'display_order', 'is_visible', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    /**
     * Public ordering: display_order ascending, then a stable secondary key
     * (name) so the section never reorders spuriously between requests.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name')->orderBy('created_at');
    }
}
