<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * An administrator's authoritative eligibility decision for a member and a
 * loan product. Decisions are append-only: a new row records each review
 * so the decision history is preserved. The current decision for any
 * member + product pair is the latest row; when none exists the pair is
 * considered "pending" administrative review.
 */
class LoanEligibilityDecision extends Model
{
    use HasUuids;

    public const ELIGIBLE = 'eligible';

    public const INELIGIBLE = 'ineligible';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'member_id', 'loan_product_id', 'status', 'decided_by', 'reason', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $decision): void {
            if ($decision->exists) {
                throw new LogicException('Loan eligibility decisions are immutable once recorded.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Loan eligibility decisions cannot be deleted.');
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}