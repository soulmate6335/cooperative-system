<?php

namespace App\Http\Controllers;

use App\Http\Requests\DisburseLoanRequest;
use App\Http\Resources\FinancialTransactionResource;
use App\Http\Resources\LoanDisbursementResource;
use App\Http\Resources\LoanOverviewResource;
use App\Models\Loan;
use App\Services\LoanDisbursementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin/finance disbursement surface. Viewing the queue and detail requires
 * loans.view (finance officers and admins); authorizing a disbursement
 * requires loans.disburse (admin/super_admin) per the existing RBAC model,
 * preserving separation of duties with financial recording.
 */
class AdminLoanDisbursementController extends Controller
{
    public function __construct(private readonly LoanDisbursementService $disbursements) {}

    public function index(Request $request): JsonResponse
    {
        // The disbursement queue is a loans area (loans.view) sitting in the
        // financial domain (payments.view). Finance officers and admins hold
        // both; committee and members do not.
        if (! $request->user()->hasPermission('loans.view') || ! $request->user()->hasPermission('payments.view')) {
            abort(403);
        }

        $query = Loan::query()
            ->where('status', Loan::STATUS_PENDING_DISBURSEMENT)
            ->whereHas('member', fn ($member) => $member->where('status', 'active'))
            ->with('member.user', 'application.product', 'application.decision.decidedBy')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($query) use ($search): void {
                $query->where('loan_number', 'like', "%{$search}%")
                    ->orWhereHas('application', fn ($application) => $application
                        ->where('application_number', 'like', "%{$search}%"))
                    ->orWhereHas('member', function ($member) use ($search): void {
                        $member->where('member_number', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        if ($request->filled('product_id')) {
            $query->whereHas('application', fn ($application) => $application
                ->where('loan_product_id', $request->string('product_id')));
        }

        $loans = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Loan disbursement queue retrieved successfully.',
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
        if (! $request->user()->hasPermission('loans.view') || ! $request->user()->hasPermission('payments.view')) {
            abort(403);
        }

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
            'message' => 'Loan disbursement retrieved successfully.',
            'data' => LoanOverviewResource::make($loan),
        ]);
    }

    public function disburse(DisburseLoanRequest $request, Loan $loan): JsonResponse
    {
        $result = $this->disbursements->disburse($loan, $request->user(), $request->validated());

        $result['loan']->load(
            'member.user',
            'application.product',
            'application.decision.decidedBy',
            'disbursement',
            'installments.allocations',
            'repaymentAllocations',
        );

        return response()->json([
            'success' => true,
            'message' => 'Loan disbursed successfully.',
            'data' => [
                'loan' => LoanOverviewResource::make($result['loan']),
                'disbursement' => LoanDisbursementResource::make($result['disbursement']),
                'transaction' => FinancialTransactionResource::make($result['transaction']),
            ],
        ]);
    }
}
