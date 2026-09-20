<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Posted member-account subledger entries used to derive account balances.
 *
 * Future organizational double-entry entries can reference these account
 * postings without changing their immutable member-balance history.
 */
class FinancialTransaction extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'account_id', 'payment_id', 'reverses_transaction_id', 'transaction_type',
        'direction', 'amount_minor', 'transaction_date', 'reference',
        'description', 'status', 'created_by', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'transaction_date' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $transaction): void {
            if ($transaction->exists && $transaction->getOriginal('status') === 'posted') {
                throw new LogicException('Posted financial transactions cannot be edited.');
            }
        });

        static::deleting(function (self $transaction): void {
            throw new LogicException('Financial transactions cannot be deleted.');
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_transaction_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_transaction_id');
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    public function signedAmount(): int
    {
        return $this->direction === 'credit' ? $this->amount_minor : -$this->amount_minor;
    }
}
