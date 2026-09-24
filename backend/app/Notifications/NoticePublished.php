<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Member-targeted notice publication. Bulk delivery to members is intentionally
 * database-channel-only (no per-member email) and synchronous by design so the
 * in-app notification never depends on a queue worker being configured.
 */
class NoticePublished extends CooperativeNotification
{
    public function __construct(
        public readonly string $noticeTitle,
        public readonly string $noticeId,
    ) {}

    public static function typeKey(): string
    {
        return 'notice.published';
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function title(): string
    {
        return 'New notice';
    }

    public function message(): string
    {
        return "A new notice has been published: {$this->noticeTitle}.";
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload('notice', $this->noticeId, $this->noticeTitle);
    }
}
