<?php

namespace App\Models;

use Database\Factories\CommitteeMeetingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configured committee meeting. The stored meeting_date is authoritative
 * for loan application submission deadlines; no calendar rule is applied.
 */
class CommitteeMeeting extends Model
{
    /** @use HasFactory<CommitteeMeetingFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'meeting_date', 'meeting_type', 'cutoff_days', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'datetime',
            'cutoff_days' => 'integer',
        ];
    }

    public function isSchedulable(): bool
    {
        return $this->status === 'scheduled';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }
}
