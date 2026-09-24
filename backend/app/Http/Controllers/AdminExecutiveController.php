<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExecutiveVisibilityRequest;
use App\Http\Requests\StoreExecutiveRequest;
use App\Http\Requests\UpdateExecutiveRequest;
use App\Http\Resources\AdminExecutiveResource;
use App\Models\Executive;
use App\Services\ExecutiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminExecutiveController extends Controller
{
    public function __construct(private readonly ExecutiveService $service) {}

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermission('executives.manage')) {
            abort(403);
        }

        $executives = $this->service->list(
            $request->only(['search', 'is_visible']),
            $request->integer('per_page', 20),
        );

        return response()->json([
            'success' => true,
            'message' => 'Executives retrieved successfully.',
            'data' => AdminExecutiveResource::collection($executives)->resolve($request),
            'meta' => [
                'current_page' => $executives->currentPage(),
                'per_page' => $executives->perPage(),
                'total' => $executives->total(),
                'last_page' => $executives->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Executive $executive): JsonResponse
    {
        Gate::authorize('view', $executive);

        return response()->json([
            'success' => true,
            'message' => 'Executive retrieved successfully.',
            'data' => AdminExecutiveResource::make($executive->load('author')),
        ]);
    }

    public function store(StoreExecutiveRequest $request): JsonResponse
    {
        $executive = $this->service->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Executive created successfully.',
            'data' => AdminExecutiveResource::make($executive->load('author')),
        ], 201);
    }

    public function update(UpdateExecutiveRequest $request, Executive $executive): JsonResponse
    {
        Gate::authorize('update', $executive);
        $executive = $this->service->update($executive, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Executive updated successfully.',
            'data' => AdminExecutiveResource::make($executive->load('author', 'updater')),
        ]);
    }

    public function visibility(ExecutiveVisibilityRequest $request, Executive $executive): JsonResponse
    {
        Gate::authorize('visibility', $executive);
        $executive = $this->service->setVisibility($executive, $request->user(), (bool) $request->validated('is_visible'));

        return response()->json([
            'success' => true,
            'message' => 'Executive visibility updated successfully.',
            'data' => AdminExecutiveResource::make($executive->load('author', 'updater')),
        ]);
    }
}
