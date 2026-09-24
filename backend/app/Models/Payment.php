<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'member_id', 'payment_method_id', 'amount_minor', 'payment_date',
        'reference_number', 'purpose', 'status', 'recorded_by', 'verified_by',
        'verified_at', 'notes', 'loan_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'payment_date' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(FinancialTransaction::class);
    }
}
