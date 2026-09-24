<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrganizationContentRequest;
use App\Http\Resources\OrganizationContentResource;
use App\Models\OrganizationContent;
use App\Services\OrganizationContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminOrganizationContentController extends Controller
{
    public function __construct(private readonly OrganizationContentService $service) {}

    public function show(Request $request): JsonResponse
    {
        Gate::authorize('view', OrganizationContent::class);

        $content = $this->service->current();

        return response()->json([
            'success' => true,
            'message' => 'Organization content retrieved successfully.',
            'data' => $content !== null ? OrganizationContentResource::make($content) : null,
        ]);
    }

    public function update(UpdateOrganizationContentRequest $request): JsonResponse
    {
        $content = $this->service->update($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Organization content updated successfully.',
            'data' => OrganizationContentResource::make($content),
        ]);
    }
}
