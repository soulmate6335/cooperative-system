<?php

namespace App\Models;

use Database\Factories\LoanProductFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configurable loan policy template. Values are defaults; final approved
 * terms are snapshotted onto loan_decisions/loans at approval.
 */
class LoanProduct extends Model
{
    /** @use HasFactory<LoanProductFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name', 'description', 'minimum_membership_months', 'minimum_amount_minor',
        'maximum_amount_minor', 'interest_rate_basis_points', 'interest_method',
        'repayment_months', 'required_guarantors', 'status',
    ];

    protected function casts(): array
    {
        return [
            'minimum_membership_months' => 'integer',
            'minimum_amount_minor' => 'integer',
            'maximum_amount_minor' => 'integer',
            'interest_rate_basis_points' => 'integer',
            'repayment_months' => 'integer',
            'required_guarantors' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function eligibilityDecisions(): HasMany
    {
        return $this->hasMany(LoanEligibilityDecision::class);
    }
}
