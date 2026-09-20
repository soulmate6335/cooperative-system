<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Committee investigation record. The committee submits findings and a
 * recommendation; it never makes the final loan decision.
 */
class LoanInvestigation extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'loan_application_id', 'assigned_to', 'investigation_date', 'member_findings',
        'savings_findings', 'shares_findings', 'existing_loan_findings',
        'guarantor_findings', 'committee_comments', 'recommendation', 'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'investigation_date' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Investigation records cannot be deleted.');
        });
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted' && $this->submitted_at !== null;
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
