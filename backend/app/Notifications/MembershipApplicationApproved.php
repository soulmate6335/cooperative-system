<?php

namespace App\Notifications;

class MembershipApplicationApproved extends CooperativeNotification
{
    public function __construct(public readonly string $applicationNumber) {}

    public static function typeKey(): string
    {
        return 'membership.application.approved';
    }

    public function title(): string
    {
        return 'Membership approved';
    }

    public function message(): string
    {
        return "Your membership application {$this->applicationNumber} has been approved. Welcome to the cooperative!";
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload('membership_application', null, $this->applicationNumber);
    }
}
