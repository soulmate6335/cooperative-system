<?php

namespace App\Notifications;

class MembershipApplicationRejected extends CooperativeNotification
{
    public function __construct(
        public readonly string $applicationNumber,
        public readonly ?string $reason = null,
    ) {}

    public static function typeKey(): string
    {
        return 'membership.application.rejected';
    }

    public function title(): string
    {
        return 'Membership application not approved';
    }

    public function message(): string
    {
        $message = "Your membership application {$this->applicationNumber} was not approved.";

        if ($this->reason !== null && trim($this->reason) !== '') {
            $message .= ' Reason: '.$this->reason.'.';
        }

        return $message;
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload('membership_application', null, $this->applicationNumber);
    }
}
