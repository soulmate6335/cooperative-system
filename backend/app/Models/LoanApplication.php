<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * A member loan application. The status machine is enforced by the
 * service layer; historical applications must never be hard-deleted.
 */
class LoanApplication extends Model
{
    use HasUuids;

    public const TERMINAL_STATUSES = ['approved', 'rejected', 'cancelled'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'application_number', 'member_id', 'loan_product_id', 'committee_meeting_id',
        'amount_requested_minor', 'purpose', 'status', 'savings_balance_minor',
        'shares_balance_minor', 'submitted_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_requested_minor' => 'integer',
            'savings_balance_minor' => 'integer',
            'shares_balance_minor' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Loan applications cannot be deleted.');
        });
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(CommitteeMeeting::class, 'committee_meeting_id');
    }

    public function guarantors(): HasMany
    {
        return $this->hasMany(LoanGuarantor::class);
    }

    public function investigation(): HasOne
    {
        return $this->hasOne(LoanInvestigation::class);
    }

    public function decision(): HasOne
    {
        return $this->hasOne(LoanDecision::class);
    }

    public function loan(): HasOne
    {
        return $this->hasOne(Loan::class);
    }

    public function acceptedGuarantorCount(): int
    {
        return $this->guarantors()->where('status', 'accepted')->count();
    }
}
