<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberLoanRepaymentRequest;
use App\Http\Resources\LoanInstallmentResource;
use App\Http\Resources\LoanOverviewResource;
use App\Http\Resources\PaymentResource;
use App\Models\Loan;
use App\Models\Member;
use App\Services\LoanRepaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Member self-service for their own loans: list/detail, repayment schedule and
 * repayment submission. Membership scoping and loan ownership are enforced
 * server-side; nothing internal to the committee/admin workflow is exposed.
 */
class MemberLoanController extends Controller
{
    public function __construct(private readonly LoanRepaymentService $repayments) {}

    public function index(Request $request): JsonResponse
    {
        $member = $this->activeMember($request);

        $loans = Loan::query()
            ->where('member_id', $member->id)
            ->with(
                'member.user',
                'application.product',
                'application.decision.decidedBy',
                'disbursement',
                'installments.allocations',
                'repaymentAllocations',
            )
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Loans retrieved successfully.',
            'data' => LoanOverviewResource::collection($loans)->resolve($request),
            'meta' => [
                'current_page' => $loans->currentPage(),
                'per_page' => $loans->perPage(),
                'total' => $loans->total(),
                'last_page' => $loans->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Loan $loan): JsonResponse
    {
        $member = $this->activeMember($request);
        abort_unless($loan->member_id === $member->id, 403);

        $loan->load(
            'member.user',
            'application.product',
            'application.decision.decidedBy',
            'disbursement',
            'installments.allocations',
            'repaymentAllocations',
        );

        return response()->json([
            'success' => true,
            'message' => 'Loan retrieved successfully.',
            'data' => LoanOverviewResource::make($loan),
        ]);
    }

    public function schedule(Request $request, Loan $loan): JsonResponse
    {
        $member = $this->activeMember($request);
        abort_unless($loan->member_id === $member->id, 403);

        $installments = $loan->installments()
            ->with('allocations')
            ->orderBy('installment_number')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Loan repayment schedule retrieved successfully.',
            'data' => LoanInstallmentResource::collection($installments),
        ]);
    }

    public function repayment(MemberLoanRepaymentRequest $request, Loan $loan): JsonResponse
    {
        $member = $this->activeMember($request);
        abort_unless($loan->member_id === $member->id, 403);

        $payment = $this->repayments->submit([
            ...$request->validated(),
            'member_id' => $member->id,
            'loan_id' => $loan->id,
        ], $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Repayment recorded and awaiting verification.',
            'data' => PaymentResource::make($payment),
        ], 201);
    }

    private function activeMember(Request $request): Member
    {
        $member = $request->user()->member;
        if (! $member || $member->status !== 'active') {
            throw ValidationException::withMessages(['member' => 'An active membership is required.']);
        }

        return $member;
    }
}
