<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A single repayment schedule obligation derived from the loan's snapshotted
 * terms. Paid amounts are derived projections of the posted repayment
 * allocations - they are not an independent source of financial truth.
 */
class LoanInstallment extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'loan_id', 'installment_number', 'due_date', 'principal_due_minor',
        'interest_due_minor', 'total_due_minor', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'installment_number' => 'integer',
            'due_date' => 'date',
            'principal_due_minor' => 'integer',
            'interest_due_minor' => 'integer',
            'total_due_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Loan installments cannot be deleted.');
        });
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LoanRepaymentAllocation::class, 'installment_id');
    }

    /** Paid principal from posted (non-voided) allocations. */
    public function paidPrincipalMinor(): int
    {
        return (int) $this->allocations()->where('status', LoanRepaymentAllocation::STATUS_POSTED)->sum('principal_allocated_minor');
    }

    /** Paid interest from posted (non-voided) allocations. */
    public function paidInterestMinor(): int
    {
        return (int) $this->allocations()->where('status', LoanRepaymentAllocation::STATUS_POSTED)->sum('interest_allocated_minor');
    }
}
