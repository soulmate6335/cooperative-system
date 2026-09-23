<?php

namespace App\Models;

use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id', 'member_number', 'profile_photo', 'joined_at', 'status',
        'membership_type', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function financialAccounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function loanGuarantees(): HasMany
    {
        return $this->hasMany(LoanGuarantor::class, 'guarantor_member_id');
    }

    public function eligibilityDecisions(): HasMany
    {
        return $this->hasMany(LoanEligibilityDecision::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
