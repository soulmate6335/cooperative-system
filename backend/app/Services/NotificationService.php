<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A user's personal notification inbox. Every operation is scoped to the
 * authenticated user: a foreign notification is never readable or mutable.
 */
class NotificationService
{
    public function paginated(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->notifications()->latest()->paginate($perPage);
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markRead(User $user, DatabaseNotification $notification): DatabaseNotification
    {
        if ($notification->notifiable_id !== $user->id || $notification->notifiable_type !== User::class) {
            // 404 rather than 403 so users cannot probe for the existence of
            // other users' notifications.
            throw (new ModelNotFoundException)->setModel(DatabaseNotification::class, [$notification->id]);
        }

        $notification->markAsRead();

        return $notification->fresh();
    }

    public function markAllRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
