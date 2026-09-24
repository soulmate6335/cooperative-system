<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminListLoanDecisionsRequest;
use App\Http\Requests\ApproveLoanRequest;
use App\Http\Requests\RejectLoanRequest;
use App\Http\Requests\ShowLoanDecisionRequest;
use App\Http\Resources\LoanApplicationResource;
use App\Http\Resources\LoanResource;
use App\Models\LoanApplication;
use App\Services\LoanDecisionService;
use App\Services\LoanEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * The final admin loan decision surface. Only the admin/super_admin roles can
 * view the queue/detail or record a decision here; the committee investigation
 * stays advisory and is never an approval.
 */
class AdminLoanDecisionController extends Controller
{
    public function __construct(
        private readonly LoanDecisionService $decisionService,
        private readonly LoanEligibilityService $eligibilityService,
    ) {}

    public function index(AdminListLoanDecisionsRequest $request): JsonResponse
    {
        $query = LoanApplication::query()
            ->where('status', 'pending_admin_decision')
            ->whereHas('member', fn ($member) => $member->where('status', 'active'))
            ->whereHas('investigation', fn ($investigation) => $investigation
                ->where('status', 'submitted')
                ->whereNotNull('submitted_at'))
            ->with('member.user', 'product', 'meeting', 'guarantors', 'investigation', 'decision', 'loan')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($query) use ($search): void {
                $query->where('application_number', 'like', "%{$search}%")
                    ->orWhereHas('member', function ($member) use ($search): void {
                        $member->where('member_number', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        if ($request->filled('product_id')) {
            $query->where('loan_product_id', $request->string('product_id'));
        }

        if ($request->filled('meeting_id')) {
            $query->where('committee_meeting_id', $request->string('meeting_id'));
        }

        $applications = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Loan decisions queue retrieved successfully.',
            'data' => LoanApplicationResource::collection($applications)->resolve($request),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
                'last_page' => $applications->lastPage(),
            ],
        ]);
    }

    public function show(ShowLoanDecisionRequest $request, LoanApplication $application): JsonResponse
    {
        $application->load(
            'member.user',
            'product',
            'meeting',
            'guarantors.guarantorMember.user',
            'investigation.assignee',
            'decision.decidedBy',
            'loan',
        );

        return response()->json([
            'success' => true,
            'message' => 'Loan decision retrieved successfully.',
            'data' => [
                'application' => LoanApplicationResource::make($application),
                'eligibility' => [
                    'decision' => $this->eligibilityService->decisionSummaryFor($application->member, $application->product),
                    'factors' => $this->eligibilityService->factorsFor($application->member, $application->product),
                ],
            ],
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
        // The final decision endpoints only operate on applications that have
        // reached the pending admin decision boundary.
        if ($application->status !== 'pending_admin_decision') {
            throw ValidationException::withMessages(['application' => 'Only applications pending admin decision can be rejected from the decision queue.']);
        }

        $application = $this->decisionService->reject($application, $request->user(), $request->validated('reason'));

        return response()->json([
            'success' => true,
            'message' => 'Loan application rejected successfully.',
            'data' => LoanApplicationResource::make($application->load('member', 'product', 'decision')),
        ]);
    }
}
