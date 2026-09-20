<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * The administrator's final decision. The approved terms on this record
 * are the historical snapshot; later loan product changes never alter it.
 */
class LoanDecision extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'loan_application_id', 'decided_by', 'decision', 'approved_amount_minor',
        'interest_rate_basis_points', 'interest_method', 'repayment_months',
        'decision_reason', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_amount_minor' => 'integer',
            'interest_rate_basis_points' => 'integer',
            'repayment_months' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $decision): void {
            if ($decision->exists) {
                throw new LogicException('Loan decisions are immutable once recorded.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Loan decisions cannot be deleted.');
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
