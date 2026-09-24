<?php

namespace App\Services;

use App\Models\Notice;
use App\Models\User;
use App\Notifications\NoticePublished;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Notice lifecycle: create drafts, edit content/targeting, publish (the only
 * event that creates member notifications) and archive. Status is exclusively
 * workflow-owned; edits never change the lifecycle state.
 */
class NoticeService
{
    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Notice::query()
            ->with('author')
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['visibility']), fn ($query) => $query->where('visibility', $filters['visibility']))
            ->when(! empty($filters['search']), function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query->where('title', 'ilike', '%'.$filters['search'].'%')
                        ->orWhere('body', 'ilike', '%'.$filters['search'].'%');
                });
            })
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function create(array $data, User $admin): Notice
    {
        return Notice::create([
            ...$data,
            'status' => Notice::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);
    }

    public function update(Notice $notice, array $data, User $admin): Notice
    {
        return DB::transaction(function () use ($notice, $data, $admin): Notice {
            $notice = Notice::query()->lockForUpdate()->findOrFail($notice->id);

            // The lifecycle status is only ever driven by publish()/archive();
            // an edit cannot sneak a state transition through the form layer.
            unset($data['status']);

            $notice->update([...$data, 'updated_by' => $admin->id]);

            return $notice->fresh();
        });
    }

    public function publish(Notice $notice, User $admin, ?CarbonInterface $publishAt = null): Notice
    {
        return DB::transaction(function () use ($notice, $admin, $publishAt): Notice {
            $notice = Notice::query()->lockForUpdate()->findOrFail($notice->id);

            if ($notice->status === Notice::STATUS_ARCHIVED) {
                throw ValidationException::withMessages(['notice' => 'Archived notices cannot be published.']);
            }

            $wasPublished = $notice->status === Notice::STATUS_PUBLISHED;
            $resolvedPublishAt = $publishAt ?? $notice->publish_at ?? now();

            if ($notice->expires_at !== null && $resolvedPublishAt->gt($notice->expires_at)) {
                throw ValidationException::withMessages(['publish_at' => 'The publish date cannot be after the expiry date.']);
            }

            $notice->update([
                'status' => Notice::STATUS_PUBLISHED,
                'publish_at' => $resolvedPublishAt,
                'updated_by' => $admin->id,
            ]);

            // Member notifications are created only by the publication event,
            // never by editing an already-published notice, so repeated edits
            // cannot flood members with duplicate notifications.
            if (! $wasPublished && $notice->visibility === Notice::VISIBILITY_MEMBERS) {
                $this->notifyActiveMembers($notice->fresh());
            }

            return $notice->fresh();
        });
    }

    public function archive(Notice $notice, User $admin): Notice
    {
        return DB::transaction(function () use ($notice, $admin): Notice {
            $notice = Notice::query()->lockForUpdate()->findOrFail($notice->id);

            $notice->update([
                'status' => Notice::STATUS_ARCHIVED,
                'updated_by' => $admin->id,
            ]);

            return $notice->fresh();
        });
    }

    /**
     * Synchronous database-channel delivery — deliberately not queued so the
     * in-app notification works without any queue worker configured.
     */
    private function notifyActiveMembers(Notice $notice): void
    {
        $members = User::query()
            ->where('status', 'active')
            ->whereHas('member', fn ($query) => $query->where('status', 'active'))
            ->get();

        Notification::send($members, new NoticePublished($notice->title, $notice->id));
    }
}
