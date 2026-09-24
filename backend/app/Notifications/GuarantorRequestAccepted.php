<?php

namespace App\Notifications;

class GuarantorRequestAccepted extends CooperativeNotification
{
    public function __construct(
        public readonly string $applicationNumber,
        public readonly string $guarantorName,
    ) {}

    public static function typeKey(): string
    {
        return 'guarantor.request.accepted';
    }

    public function title(): string
    {
        return 'Guarantor request accepted';
    }

    public function message(): string
    {
        return "{$this->guarantorName} accepted your guarantor request on loan application {$this->applicationNumber}.";
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload('loan_application', null, $this->applicationNumber);
    }
}
