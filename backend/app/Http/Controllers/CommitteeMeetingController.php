<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommitteeMeetingRequest;
use App\Http\Requests\UpdateCommitteeMeetingRequest;
use App\Http\Resources\CommitteeMeetingResource;
use App\Models\CommitteeMeeting;
use App\Services\CommitteeMeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommitteeMeetingController extends Controller
{
    public function __construct(private readonly CommitteeMeetingService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage', CommitteeMeeting::class);

        $meetings = CommitteeMeeting::query()->orderByDesc('meeting_date')->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Committee meetings retrieved successfully.',
            'data' => CommitteeMeetingResource::collection($meetings)->resolve($request),
            'meta' => [
                'current_page' => $meetings->currentPage(),
                'per_page' => $meetings->perPage(),
                'total' => $meetings->total(),
                'last_page' => $meetings->lastPage(),
            ],
        ]);
    }

    public function store(StoreCommitteeMeetingRequest $request): JsonResponse
    {
        $meeting = $this->service->create($request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Committee meeting created successfully.',
            'data' => CommitteeMeetingResource::make($meeting),
        ], 201);
    }

    public function update(UpdateCommitteeMeetingRequest $request, CommitteeMeeting $meeting): JsonResponse
    {
        $meeting = $this->service->update($meeting, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Committee meeting updated successfully.',
            'data' => CommitteeMeetingResource::make($meeting),
        ]);
    }
}
