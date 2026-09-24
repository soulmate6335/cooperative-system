<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A posted repayment allocation arrowed at a single installment. Amounts are
 * immutable once created; reversing the funding Financial Core transaction
 * transitions the row to "voided" instead of editing or deleting history.
 */
class LoanRepaymentAllocation extends Model
{
    use HasUuids;

    public const STATUS_POSTED = 'posted';

    public const STATUS_VOIDED = 'voided';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'payment_id', 'financial_transaction_id', 'loan_id', 'installment_id',
        'amount_minor', 'principal_allocated_minor', 'interest_allocated_minor',
        'status', 'voided_by_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'principal_allocated_minor' => 'integer',
            'interest_allocated_minor' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Loan repayment allocations cannot be deleted.');
        });

        static::saving(function (self $allocation): void {
            if (! $allocation->exists) {
                return;
            }

            $originalStatus = $allocation->getOriginal('status');

            if ($originalStatus === self::STATUS_VOIDED) {
                throw new LogicException('Voided repayment allocations cannot be edited.');
            }

            // The only legal transition off a posted row is the posted -> voided
            // switch performed by a repayment reversal.
            if ($originalStatus === self::STATUS_POSTED && $allocation->status === self::STATUS_POSTED) {
                foreach (['amount_minor', 'principal_allocated_minor', 'interest_allocated_minor', 'installment_id'] as $field) {
                    if ($allocation->getOriginal($field) != $allocation->getAttribute($field)) {
                        throw new LogicException('Posted repayment allocation amounts cannot be edited.');
                    }
                }
            }
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(LoanInstallment::class, 'installment_id');
    }
}
