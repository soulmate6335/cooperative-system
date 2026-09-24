<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommitteeListLoanApplicationsRequest;
use App\Http\Requests\ShowCommitteeApplicationRequest;
use App\Http\Requests\StartInvestigationRequest;
use App\Http\Requests\SubmitInvestigationRequest;
use App\Http\Requests\UpdateInvestigationRequest;
use App\Http\Resources\LoanApplicationResource;
use App\Http\Resources\LoanInvestigationResource;
use App\Models\LoanApplication;
use App\Models\LoanInvestigation;
use App\Services\LoanInvestigationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CommitteeLoanController extends Controller
{
    public function __construct(private readonly LoanInvestigationService $service) {}

    public function index(CommitteeListLoanApplicationsRequest $request): JsonResponse
    {
        $user = $request->user();

        $query = LoanApplication::query()
            ->where(function ($query) use ($user): void {
                // Actionable work already assigned to this officer...
                $query->whereHas('investigation', fn ($investigation) => $investigation
                    ->where('assigned_to', $user->id)
                    ->where('status', '!=', 'submitted'))
                    // ...plus applications ready for committee review that nobody
                    // has claimed yet (RA-5 queue eligibility).
                    ->orWhere(function ($openQueue): void {
                        $openQueue->where('status', 'guarantors_confirmed')
                            ->whereHas('member', fn ($member) => $member->where('status', 'active'))
                            ->whereHas('meeting')
                            ->whereDoesntHave('investigation');
                    });
            })
            ->whereNotIn('status', LoanApplication::TERMINAL_STATUSES)
            ->with('member.user', 'product', 'meeting', 'guarantors', 'investigation')
            ->orderByDesc('created_at');

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

    public function show(ShowCommitteeApplicationRequest $request, LoanApplication $application): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Loan application retrieved successfully.',
            'data' => LoanApplicationResource::make($application->load('member.user', 'product', 'meeting', 'guarantors.guarantorMember.user', 'investigation')),
        ]);
    }

    public function startInvestigation(StartInvestigationRequest $request, LoanApplication $application): JsonResponse
    {
        $investigation = $this->service->assign($application, $request->user()->id);

        $application = $application->fresh()->load('member.user', 'product', 'meeting', 'guarantors.guarantorMember.user', 'investigation');

        return response()->json([
            'success' => true,
            'message' => 'Investigation started successfully.',
            'data' => [
                'investigation' => LoanInvestigationResource::make($investigation),
                'application' => LoanApplicationResource::make($application),
            ],
        ], 201);
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

        $application = $application->fresh()->load('member.user', 'product', 'meeting', 'guarantors.guarantorMember.user', 'investigation');

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
