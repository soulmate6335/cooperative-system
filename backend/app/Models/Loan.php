<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * The approved loan record created at admin approval. It begins in the
 * pending_disbursement boundary state; approval is never a disbursement.
 * Terms are a snapshot of the approved decision.
 */
class Loan extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'loan_application_id', 'member_id', 'loan_number', 'principal_amount_minor',
        'interest_rate_basis_points', 'interest_method', 'repayment_months',
        'interest_amount_minor', 'total_payable_minor', 'disbursed_amount_minor',
        'disbursed_at', 'start_date', 'maturity_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount_minor' => 'integer',
            'interest_rate_basis_points' => 'integer',
            'repayment_months' => 'integer',
            'interest_amount_minor' => 'integer',
            'total_payable_minor' => 'integer',
            'disbursed_amount_minor' => 'integer',
            'disbursed_at' => 'datetime',
            'start_date' => 'datetime',
            'maturity_date' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Loan records cannot be deleted.');
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
