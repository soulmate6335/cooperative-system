<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelGuarantorRequest;
use App\Http\Requests\RespondGuarantorRequest;
use App\Http\Requests\StoreLoanGuarantorRequest;
use App\Http\Resources\LoanGuarantorResource;
use App\Models\LoanApplication;
use App\Models\LoanGuarantor;
use App\Services\LoanGuarantorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoanGuarantorController extends Controller
{
    public function __construct(private readonly LoanGuarantorService $service) {}

    public function store(StoreLoanGuarantorRequest $request, LoanApplication $application): JsonResponse
    {
        $member = $request->user()->member;
        if ($member === null) {
            throw ValidationException::withMessages(['member' => 'Only registered members can request loan guarantors.']);
        }

        $guarantor = $this->service->request($application, $member, $request->validated('guarantor_member_id'));

        return response()->json([
            'success' => true,
            'message' => 'Guarantor requested successfully.',
            'data' => LoanGuarantorResource::make($guarantor->load('guarantorMember')),
        ], 201);
    }

    public function requests(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('loans.guarantee')) {
            abort(403, 'You are not authorized to view guarantee requests.');
        }

        $member = $user->member;
        if ($member === null) {
            return response()->json([
                'success' => true,
                'message' => 'Guarantee requests retrieved successfully.',
                'data' => [],
                'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1],
            ]);
        }

        $requests = $member->loanGuarantees()
            ->with('application.member', 'application.product')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Guarantee requests retrieved successfully.',
            'data' => LoanGuarantorResource::collection($requests)->resolve($request),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    public function accept(RespondGuarantorRequest $request, LoanGuarantor $guarantor): JsonResponse
    {
        $guarantor = $this->service->accept($guarantor, $request->user(), $request->validated('response_note'));

        return response()->json([
            'success' => true,
            'message' => 'Guarantee accepted successfully.',
            'data' => LoanGuarantorResource::make($guarantor->load('guarantorMember')),
        ]);
    }

    public function decline(RespondGuarantorRequest $request, LoanGuarantor $guarantor): JsonResponse
    {
        $guarantor = $this->service->decline($guarantor, $request->user(), $request->validated('response_note'));

        return response()->json([
            'success' => true,
            'message' => 'Guarantee declined successfully.',
            'data' => LoanGuarantorResource::make($guarantor->load('guarantorMember')),
        ]);
    }

    public function cancel(CancelGuarantorRequest $request, LoanApplication $application, LoanGuarantor $guarantor): JsonResponse
    {
        $guarantor = $this->service->cancelPendingRequest($guarantor, $request->user()->member);

        return response()->json([
            'success' => true,
            'message' => 'Guarantee request cancelled successfully.',
            'data' => LoanGuarantorResource::make($guarantor->load('guarantorMember')),
        ]);
    }
}
