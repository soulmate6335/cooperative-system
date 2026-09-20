<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminCancelLoanApplicationRequest;
use App\Http\Requests\AdminListLoanApplicationsRequest;
use App\Http\Requests\ApproveLoanRequest;
use App\Http\Requests\AssignInvestigationRequest;
use App\Http\Requests\RejectLoanRequest;
use App\Http\Resources\LoanApplicationResource;
use App\Http\Resources\LoanInvestigationResource;
use App\Http\Resources\LoanResource;
use App\Models\LoanApplication;
use App\Services\LoanApplicationService;
use App\Services\LoanDecisionService;
use App\Services\LoanInvestigationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AdminLoanController extends Controller
{
    public function __construct(
        private readonly LoanApplicationService $applicationService,
        private readonly LoanInvestigationService $investigationService,
        private readonly LoanDecisionService $decisionService,
    ) {}

    public function index(AdminListLoanApplicationsRequest $request): JsonResponse
    {
        $query = LoanApplication::query()
            ->with('member', 'product', 'meeting', 'guarantors', 'investigation', 'decision', 'loan')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($query) use ($search): void {
                $query->where('application_number', 'like', "%{$search}%")
                    ->orWhereHas('member', fn ($member) => $member->where('member_number', 'like', "%{$search}%"));
            });
        }

        $applications = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Loan applications retrieved successfully.',
            'data' => LoanApplicationResource::collection($applications)->resolve($request),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
                'last_page' => $applications->lastPage(),
            ],
        ]);
    }

    public function show(LoanApplication $application): JsonResponse
    {
        Gate::authorize('view', $application);

        return response()->json([
            'success' => true,
            'message' => 'Loan application retrieved successfully.',
            'data' => LoanApplicationResource::make($application->load('member', 'product', 'meeting', 'guarantors', 'investigation', 'decision', 'loan')),
        ]);
    }

    public function assign(AssignInvestigationRequest $request, LoanApplication $application): JsonResponse
    {
        $investigation = $this->investigationService->assign($application, $request->validated('assigned_to'));

        return response()->json([
            'success' => true,
            'message' => 'Investigation assigned successfully.',
            'data' => LoanInvestigationResource::make($investigation),
        ], 201);
    }

    public function cancel(AdminCancelLoanApplicationRequest $request, LoanApplication $application): JsonResponse
    {
        $application = $this->applicationService->cancelByAdmin($application, $request->validated('reason', null));

        return response()->json([
            'success' => true,
            'message' => 'Loan application cancelled successfully.',
            'data' => LoanApplicationResource::make($application->load('member', 'product')),
        ]);
    }

    public function approve(ApproveLoanRequest $request, LoanApplication $application): JsonResponse
    {
        $loan = $this->decisionService->approve($application, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Loan application approved successfully.',
            'data' => LoanResource::make($loan),
        ]);
    }

    public function reject(RejectLoanRequest $request, LoanApplication $application): JsonResponse
    {
        $application = $this->decisionService->reject($application, $request->user(), $request->validated('reason'));

        return response()->json([
            'success' => true,
            'message' => 'Loan application rejected successfully.',
            'data' => LoanApplicationResource::make($application->load('member', 'product', 'decision')),
        ]);
    }
}
