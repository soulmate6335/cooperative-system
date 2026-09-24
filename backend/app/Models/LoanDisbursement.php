<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * The single full-disbursement record for a loan. Posting a disbursement is a
 * one-way event: the record preserves the actual amount disbursed and the
 * authorized officer, and the Financial Core transaction is the authoritative
 * money movement.
 */
class LoanDisbursement extends Model
{
    use HasUuids;

    public const STATUS_POSTED = 'posted';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'loan_id', 'amount_minor', 'payment_method_id', 'financial_account_id',
        'reference', 'status', 'disbursed_at', 'authorized_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'disbursed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Loan disbursement records cannot be deleted.');
        });
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }
}
