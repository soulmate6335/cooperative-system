<?php

namespace App\Notifications;

class LoanApplicationSubmitted extends CooperativeNotification
{
    public function __construct(public readonly string $applicationNumber) {}

    public static function typeKey(): string
    {
        return 'loan.application.submitted';
    }

    public function title(): string
    {
        return 'Loan application submitted';
    }

    public function message(): string
    {
        return "Your loan application {$this->applicationNumber} has been submitted for review.";
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload('loan_application', null, $this->applicationNumber);
    }
}
