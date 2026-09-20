<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelLoanApplicationRequest;
use App\Http\Requests\StoreLoanApplicationRequest;
use App\Http\Requests\SubmitLoanApplicationRequest;
use App\Http\Resources\LoanApplicationResource;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\LoanApplicationService;
use App\Services\LoanEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LoanApplicationController extends Controller
{
    public function __construct(
        private readonly LoanApplicationService $service,
        private readonly LoanEligibilityService $eligibility,
    ) {}

    public function eligibility(Request $request): JsonResponse
    {
        Gate::authorize('create', LoanApplication::class);

        $member = $request->user()->member;
        if ($member === null) {
            throw ValidationException::withMessages(['member' => 'Only registered members can check loan eligibility.']);
        }

        $request->validate(['product_id' => ['required', 'uuid', 'exists:loan_products,id']]);
        $product = LoanProduct::query()->findOrFail($request->string('product_id'));

        return response()->json([
            'success' => true,
            'message' => 'Loan eligibility factors retrieved successfully.',
            'data' => $this->eligibility->factorsFor($member, $product),
        ]);
    }

    public function store(StoreLoanApplicationRequest $request): JsonResponse
    {
        $member = $request->user()->member;
        if ($member === null) {
            throw ValidationException::withMessages(['member' => 'Only registered members can apply for loans.']);
        }

        $application = $this->service->create($member, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Loan application created successfully.',
            'data' => LoanApplicationResource::make($application->load('member', 'product')),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('create', LoanApplication::class);

        $member = $request->user()->member;
        if ($member === null) {
            return response()->json([
                'success' => true,
                'message' => 'Loan applications retrieved successfully.',
                'data' => [],
                'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1],
            ]);
        }

        $query = $member->loanApplications()->with('member', 'product', 'meeting', 'guarantors')->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
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

    public function submit(SubmitLoanApplicationRequest $request, LoanApplication $application): JsonResponse
    {
        $application = $this->service->submit($application);

        return response()->json([
            'success' => true,
            'message' => 'Loan application submitted successfully.',
            'data' => LoanApplicationResource::make($application->load('member', 'product', 'meeting', 'guarantors')),
        ]);
    }

    public function cancel(CancelLoanApplicationRequest $request, LoanApplication $application): JsonResponse
    {
        $application = $this->service->cancelByMember($application, $request->user()->member);

        return response()->json([
            'success' => true,
            'message' => 'Loan application cancelled successfully.',
            'data' => LoanApplicationResource::make($application->load('member', 'product')),
        ]);
    }
}
