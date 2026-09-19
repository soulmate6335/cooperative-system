<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListMemberApplicationsRequest;
use App\Http\Requests\RejectMemberApplicationRequest;
use App\Http\Requests\StoreMemberApplicationRequest;
use App\Http\Resources\MemberApplicationResource;
use App\Http\Resources\MemberResource;
use App\Models\MemberApplication;
use App\Services\MembershipApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MemberApplicationController extends Controller
{
    public function __construct(private readonly MembershipApplicationService $service) {}

    public function store(StoreMemberApplicationRequest $request): JsonResponse
    {
        $application = $this->service->submit($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Membership application submitted successfully.',
            'data' => MemberApplicationResource::make($application),
        ], 201);
    }

    public function index(ListMemberApplicationsRequest $request): JsonResponse
    {
        $query = MemberApplication::query()->with('reviewer')->latest('submitted_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($query) use ($search): void {
                $query->where('application_number', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $applications = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Membership applications retrieved successfully.',
            'data' => MemberApplicationResource::collection($applications)->resolve($request),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
                'last_page' => $applications->lastPage(),
            ],
        ]);
    }

    public function show(MemberApplication $application): JsonResponse
    {
        Gate::authorize('view', $application);

        return response()->json([
            'success' => true,
            'message' => 'Membership application retrieved successfully.',
            'data' => MemberApplicationResource::make($application->load('reviewer')),
        ]);
    }

    public function approve(Request $request, MemberApplication $application): JsonResponse
    {
        Gate::authorize('approve', $application);
        $member = $this->service->approve($application, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Membership application approved successfully.',
            'data' => MemberResource::make($member),
        ]);
    }

    public function reject(RejectMemberApplicationRequest $request, MemberApplication $application): JsonResponse
    {
        $application = $this->service->reject($application, $request->user(), $request->validated('reason'));

        return response()->json([
            'success' => true,
            'message' => 'Membership application rejected successfully.',
            'data' => MemberApplicationResource::make($application),
        ]);
    }
}
