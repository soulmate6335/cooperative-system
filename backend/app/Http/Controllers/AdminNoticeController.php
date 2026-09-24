<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublishNoticeRequest;
use App\Http\Requests\StoreNoticeRequest;
use App\Http\Requests\UpdateNoticeRequest;
use App\Http\Resources\AdminNoticeResource;
use App\Models\Notice;
use App\Services\NoticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminNoticeController extends Controller
{
    public function __construct(private readonly NoticeService $service) {}

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermission('notices.manage')) {
            abort(403);
        }

        $notices = $this->service->list(
            $request->only(['status', 'visibility', 'search']),
            $request->integer('per_page', 20),
        );

        return response()->json([
            'success' => true,
            'message' => 'Notices retrieved successfully.',
            'data' => AdminNoticeResource::collection($notices)->resolve($request),
            'meta' => [
                'current_page' => $notices->currentPage(),
                'per_page' => $notices->perPage(),
                'total' => $notices->total(),
                'last_page' => $notices->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Notice $notice): JsonResponse
    {
        Gate::authorize('view', $notice);

        return response()->json([
            'success' => true,
            'message' => 'Notice retrieved successfully.',
            'data' => AdminNoticeResource::make($notice->load('author', 'updater')),
        ]);
    }

    public function store(StoreNoticeRequest $request): JsonResponse
    {
        $notice = $this->service->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Notice created successfully.',
            'data' => AdminNoticeResource::make($notice->load('author')),
        ], 201);
    }

    public function update(UpdateNoticeRequest $request, Notice $notice): JsonResponse
    {
        Gate::authorize('update', $notice);
        $notice = $this->service->update($notice, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Notice updated successfully.',
            'data' => AdminNoticeResource::make($notice->load('author', 'updater')),
        ]);
    }

    public function publish(PublishNoticeRequest $request, Notice $notice): JsonResponse
    {
        Gate::authorize('publish', $notice);
        $notice = $this->service->publish($notice, $request->user(), $request->date('publish_at'));

        return response()->json([
            'success' => true,
            'message' => 'Notice published successfully.',
            'data' => AdminNoticeResource::make($notice->load('author', 'updater')),
        ]);
    }

    public function archive(Request $request, Notice $notice): JsonResponse
    {
        Gate::authorize('archive', $notice);
        $notice = $this->service->archive($notice, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Notice archived successfully.',
            'data' => AdminNoticeResource::make($notice->load('author', 'updater')),
        ]);
    }
}
