<?php

namespace App\Notifications;

class LoanDisbursed extends CooperativeNotification
{
    public function __construct(
        public readonly string $loanNumber,
        public readonly string $loanId,
    ) {}

    public static function typeKey(): string
    {
        return 'loan.disbursed';
    }

    public function title(): string
    {
        return 'Loan disbursed';
    }

    public function message(): string
    {
        return "Your loan {$this->loanNumber} has been disbursed.";
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload('loan', $this->loanId, $this->loanNumber);
    }
}
