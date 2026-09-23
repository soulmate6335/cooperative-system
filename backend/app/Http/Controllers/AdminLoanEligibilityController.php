<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecideLoanEligibilityRequest;
use App\Http\Resources\LoanProductResource;
use App\Http\Resources\MemberResource;
use App\Models\LoanEligibilityDecision;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Services\LoanEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Administrative loan eligibility review. Only authorized administrators may
 * view assessments or record decisions; members can never decide their own
 * eligibility.
 */
class AdminLoanEligibilityController extends Controller
{
    public function __construct(private readonly LoanEligibilityService $eligibility) {}

    public function members(Request $request): JsonResponse
    {
        Gate::authorize('view', LoanEligibilityDecision::class);

        $query = Member::query()->with('user')->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($query) use ($search): void {
                $query->where('member_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $members = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Members retrieved successfully.',
            'data' => MemberResource::collection($members)->resolve($request),
            'meta' => [
                'current_page' => $members->currentPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
                'last_page' => $members->lastPage(),
            ],
        ]);
    }

    public function assessment(Request $request): JsonResponse
    {
        Gate::authorize('view', LoanEligibilityDecision::class);

        $request->validate([
            'member_id' => ['required', 'uuid', 'exists:members,id'],
            'product_id' => ['required', 'uuid', 'exists:loan_products,id'],
        ]);

        $member = Member::query()->with('user')->findOrFail($request->string('member_id'));
        $product = LoanProduct::query()->findOrFail($request->string('product_id'));

        $factors = $this->eligibility->factorsFor($member, $product);
        $factors['admin_decision'] = $this->eligibility->decisionSummaryFor($member, $product);

        return response()->json([
            'success' => true,
            'message' => 'Eligibility assessment retrieved successfully.',
            'data' => [
                'member' => MemberResource::make($member),
                'product' => LoanProductResource::make($product),
                'factors' => $factors,
            ],
        ]);
    }

    public function decide(DecideLoanEligibilityRequest $request): JsonResponse
    {
        $member = Member::query()->findOrFail($request->string('member_id'));
        $product = LoanProduct::query()->findOrFail($request->string('loan_product_id'));

        $this->eligibility->recordDecision(
            $member,
            $product,
            $request->user(),
            $request->string('status')->toString(),
            $request->string('reason')->toString(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Eligibility decision recorded successfully.',
            'data' => [
                'member_id' => $member->id,
                'loan_product_id' => $product->id,
                'admin_decision' => $this->eligibility->decisionSummaryFor($member, $product),
            ],
        ], 201);
    }
}