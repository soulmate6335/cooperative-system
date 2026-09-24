<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Shared base for cooperative in-app notifications.
 *
 * The database channel is always on and is the source of truth. The mail
 * channel is only attached when the application is explicitly configured for
 * mail delivery (config('mail.enabled')), so a missing mail configuration in
 * local development can never break the in-app notification.
 */
abstract class CooperativeNotification extends Notification
{
    /** Stable machine key surfaced by the notification API (e.g. 'loan.approved'). */
    abstract public static function typeKey(): string;

    abstract public function title(): string;

    abstract public function message(): string;

    public function via(object $notifiable): array
    {
        return config('mail.enabled', false) ? ['database', 'mail'] : ['database'];
    }

    /**
     * Standard structured database payload consumed by NotificationResource.
     *
     * @return array{title: string, message: string, type_key: string, reference_type: string|null, reference_id: string|null, reference: string|null}
     */
    protected function payload(?string $referenceType = null, ?string $referenceId = null, ?string $reference = null): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            'type_key' => static::typeKey(),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reference' => $reference,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->message());
    }

    protected function formatMinor(int $minor): string
    {
        return '₦'.number_format($minor / 100, 2);
    }
}
