<?php

namespace App\Notifications;

class LoanRejected extends CooperativeNotification
{
    public function __construct(
        public readonly string $applicationNumber,
        public readonly ?string $reason = null,
    ) {}

    public static function typeKey(): string
    {
        return 'loan.application.rejected';
    }

    public function title(): string
    {
        return 'Loan application not approved';
    }

    public function message(): string
    {
        $message = "Your loan application {$this->applicationNumber} was not approved.";

        if ($this->reason !== null && trim($this->reason) !== '') {
            $message .= ' Reason: '.$this->reason.'.';
        }

        return $message;
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload('loan_application', null, $this->applicationNumber);
    }
}
