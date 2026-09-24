<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Authenticated personal notification inbox. Every route is scoped to the
 * current user by NotificationService; foreign notifications are unreachable.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $notifications = $this->service->paginated($request->user(), $request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully.',
            'data' => NotificationResource::collection($notifications)->resolve($request),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Unread notification count retrieved successfully.',
            'data' => ['unread_count' => $this->service->unreadCount($request->user())],
        ]);
    }

    public function read(Request $request, DatabaseNotification $notification): JsonResponse
    {
        $notification = $this->service->markRead($request->user(), $notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => NotificationResource::make($notification),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $count = $this->service->markAllRead($request->user());

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
            'data' => ['marked_read' => $count],
        ]);
    }
}
