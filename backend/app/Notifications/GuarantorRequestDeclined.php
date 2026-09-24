<?php

namespace App\Notifications;

class GuarantorRequestDeclined extends CooperativeNotification
{
    public function __construct(
        public readonly string $applicationNumber,
        public readonly string $guarantorName,
    ) {}

    public static function typeKey(): string
    {
        return 'guarantor.request.declined';
    }

    public function title(): string
    {
        return 'Guarantor request declined';
    }

    public function message(): string
    {
        return "{$this->guarantorName} declined your guarantor request on loan application {$this->applicationNumber}.";
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload('loan_application', null, $this->applicationNumber);
    }
}
