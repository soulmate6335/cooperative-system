<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * The approved loan record created at admin approval. It begins in the
 * pending_disbursement boundary state; approval is never a disbursement.
 * Terms are a snapshot of the approved decision.
 */
class Loan extends Model
{
    use HasUuids;

    public const STATUS_PENDING_DISBURSEMENT = 'pending_disbursement';

    public const STATUS_DISBURSED = 'disbursed';

    public const STATUS_COMPLETED = 'completed';

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

    public function disbursement(): HasOne
    {
        return $this->hasOne(LoanDisbursement::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }

    public function repaymentAllocations(): HasMany
    {
        return $this->hasMany(LoanRepaymentAllocation::class);
    }

    /**
     * Derived obligation figures: the sum of the loan's installment obligations
     * minus posted repayment allocations. This is intentionally NOT a mutable
     * balance column; the Financial Core transactions remain authoritative for
     * actual money movement.
     */
    public function obligationSummary(): array
    {
        $installments = $this->relationLoaded('installments')
            ? $this->installments
            : $this->installments()->get();

        $totalPrincipalDue = (int) $installments->sum('principal_due_minor');
        $totalInterestDue = (int) $installments->sum('interest_due_minor');

        if ($totalPrincipalDue > 0) {
            $allAllocations = $this->relationLoaded('repaymentAllocations')
                ? $this->repaymentAllocations
                : $this->repaymentAllocations()->get();
            $allocations = $allAllocations->where('status', LoanRepaymentAllocation::STATUS_POSTED);
        } else {
            $allocations = collect();
        }

        $principalPaid = (int) $allocations->sum('principal_allocated_minor');
        $interestPaid = (int) $allocations->sum('interest_allocated_minor');
        $totalPaid = $principalPaid + $interestPaid;

        $totalPrincipal = $totalPrincipalDue > 0 ? $totalPrincipalDue : (int) $this->principal_amount_minor;
        $totalObligation = $totalPrincipalDue > 0
            ? $totalPrincipalDue + $totalInterestDue
            : (int) ($this->total_payable_minor ?? $this->principal_amount_minor);

        $nextDue = null;
        $paidByInstallment = $allocations->groupBy('installment_id');
        foreach ($installments->sortBy('installment_number') as $installment) {
            $paid = $paidByInstallment->get($installment->id, collect());
            $paidPrincipal = (int) $paid->sum('principal_allocated_minor');
            $paidInterest = (int) $paid->sum('interest_allocated_minor');
            if ($paidPrincipal < $installment->principal_due_minor || $paidInterest < $installment->interest_due_minor) {
                $nextDue = $installment;
                break;
            }
        }

        return [
            'total_principal_minor' => $totalPrincipal,
            'total_interest_minor' => $totalInterestDue,
            'total_obligation_minor' => $totalObligation,
            'principal_paid_minor' => $principalPaid,
            'interest_paid_minor' => $interestPaid,
            'total_paid_minor' => $totalPaid,
            'principal_outstanding_minor' => $totalPrincipal - $principalPaid,
            'interest_outstanding_minor' => $totalInterestDue - $interestPaid,
            'total_outstanding_minor' => $totalObligation - $totalPaid,
            'next_due_installment_id' => $nextDue?->id,
        ];
    }
}
