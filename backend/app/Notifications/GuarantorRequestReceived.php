<?php

namespace App\Notifications;

class GuarantorRequestReceived extends CooperativeNotification
{
    public function __construct(
        public readonly string $applicationNumber,
        public readonly string $applicationId,
    ) {}

    public static function typeKey(): string
    {
        return 'guarantor.request.received';
    }

    public function title(): string
    {
        return 'Guarantor request';
    }

    public function message(): string
    {
        return "You have been requested to guarantee loan application {$this->applicationNumber}.";
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload('loan_application', $this->applicationId, $this->applicationNumber);
    }
}
