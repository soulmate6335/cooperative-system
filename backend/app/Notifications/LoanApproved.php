<?php

namespace App\Notifications;

class LoanApproved extends CooperativeNotification
{
    public function __construct(
        public readonly string $loanNumber,
        public readonly string $loanId,
    ) {}

    public static function typeKey(): string
    {
        return 'loan.approved';
    }

    public function title(): string
    {
        return 'Loan approved';
    }

    public function message(): string
    {
        return "Your loan {$this->loanNumber} has been approved and is awaiting disbursement.";
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload('loan', $this->loanId, $this->loanNumber);
    }
}
