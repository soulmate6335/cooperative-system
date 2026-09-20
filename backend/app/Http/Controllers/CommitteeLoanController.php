<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommitteeListLoanApplicationsRequest;
use App\Http\Requests\SubmitInvestigationRequest;
use App\Http\Requests\UpdateInvestigationRequest;
use App\Http\Resources\LoanApplicationResource;
use App\Http\Resources\LoanInvestigationResource;
use App\Models\LoanApplication;
use App\Models\LoanInvestigation;
use App\Services\LoanInvestigationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CommitteeLoanController extends Controller
{
    public function __construct(private readonly LoanInvestigationService $service) {}

    public function index(CommitteeListLoanApplicationsRequest $request): JsonResponse
    {
        $query = LoanApplication::query()
            ->whereHas('investigation', fn ($investigation) => $investigation->where('assigned_to', $request->user()->id))
            ->with('member', 'product', 'meeting', 'guarantors', 'investigation')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $applications = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Assigned loan applications retrieved successfully.',
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
        Gate::authorize('review', $application);

        return response()->json([
            'success' => true,
            'message' => 'Loan application retrieved successfully.',
            'data' => LoanApplicationResource::make($application->load('member', 'product', 'meeting', 'guarantors', 'investigation')),
        ]);
    }

    public function updateInvestigation(UpdateInvestigationRequest $request, LoanApplication $application): JsonResponse
    {
        $investigation = $this->investigationOrFail($application);
        $investigation = $this->service->update($investigation, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Investigation updated successfully.',
            'data' => LoanInvestigationResource::make($investigation),
        ]);
    }

    public function submitInvestigation(SubmitInvestigationRequest $request, LoanApplication $application): JsonResponse
    {
        $investigation = $this->investigationOrFail($application);
        $investigation = $this->service->submit($investigation, $request->validated(), $request->user());

        $application = $application->fresh()->load('member', 'product', 'meeting', 'guarantors', 'investigation');

        return response()->json([
            'success' => true,
            'message' => 'Investigation submitted successfully.',
            'data' => [
                'investigation' => LoanInvestigationResource::make($investigation),
                'application' => LoanApplicationResource::make($application),
            ],
        ]);
    }

    private function investigationOrFail(LoanApplication $application): LoanInvestigation
    {
        $investigation = $application->investigation;

        if (! $investigation instanceof LoanInvestigation) {
            throw ValidationException::withMessages(['investigation' => 'No investigation has been assigned to this application.']);
        }

        return $investigation;
    }
}
