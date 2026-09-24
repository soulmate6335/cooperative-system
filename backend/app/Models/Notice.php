<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Organization notice with a draft -> published -> archived lifecycle and a
 * public/members visibility target. Visibility is purely temporal + status
 * driven: drafts, future-scheduled and expired notices are never surfaced.
 */
class Notice extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_MEMBERS = 'members';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'title', 'body', 'excerpt', 'status', 'visibility',
        'publish_at', 'expires_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'expires_at' => 'datetime',
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

    /**
     * A notice is currently visible only when it is published and the current
     * time falls inside its publish/expiry window (if either is supplied).
     */
    public function isCurrentlyVisible(?CarbonInterface $at = null): bool
    {
        $now = $at ?? now();

        if ($this->status !== self::STATUS_PUBLISHED) {
            return false;
        }

        if ($this->publish_at !== null && $this->publish_at->gt($now)) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->lte($now)) {
            return false;
        }

        return true;
    }

    public function isPubliclyVisible(?CarbonInterface $at = null): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC && $this->isCurrentlyVisible($at);
    }

    /** Members see every currently visible notice (public and member-targeted). */
    public function isMemberVisible(?CarbonInterface $at = null): bool
    {
        return $this->isCurrentlyVisible($at);
    }

    public function scopeVisibleNow(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $now = $at ?? now();

        return $query->where('status', self::STATUS_PUBLISHED)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('publish_at')
                ->orWhere('publish_at', '<=', $now))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', $now));
    }

    public function scopePublicVisible(Builder $query, ?CarbonInterface $at = null): Builder
    {
        return $query->visibleNow($at)->where('visibility', self::VISIBILITY_PUBLIC);
    }

    public function scopeMemberVisible(Builder $query, ?CarbonInterface $at = null): Builder
    {
        return $query->visibleNow($at);
    }
}
