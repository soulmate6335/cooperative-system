<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanInvestigation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanInvestigationService
{
    public function assign(LoanApplication $application, string $assignedToUserId): LoanInvestigation
    {
        return DB::transaction(function () use ($application, $assignedToUserId): LoanInvestigation {
            $application = LoanApplication::query()->lockForUpdate()->find($application->id);

            if ($application->member->status !== 'active') {
                throw ValidationException::withMessages(['member' => 'The application is frozen while the member is suspended.']);
            }

            if ($application->status !== 'guarantors_confirmed') {
                throw ValidationException::withMessages(['application' => 'An investigation can only begin once the required guarantors are confirmed.']);
            }

            if ($application->investigation()->exists()) {
                throw ValidationException::withMessages(['application' => 'An investigation has already been assigned to this application.']);
            }

            $investigation = LoanInvestigation::create([
                'loan_application_id' => $application->id,
                'assigned_to' => $assignedToUserId,
                'status' => 'assigned',
            ]);

            $application->update(['status' => 'under_investigation']);

            return $investigation->fresh();
        });
    }

    public function update(LoanInvestigation $investigation, array $data, User $officer): LoanInvestigation
    {
        return DB::transaction(function () use ($investigation, $data, $officer): LoanInvestigation {
            $investigation = LoanInvestigation::query()->lockForUpdate()->find($investigation->id);

            if ($investigation->assigned_to !== $officer->id) {
                throw ValidationException::withMessages(['investigation' => 'Only the assigned investigator can update this investigation.']);
            }

            if (! in_array($investigation->status, ['assigned', 'in_progress', 'returned'], true)) {
                throw ValidationException::withMessages(['investigation' => 'A submitted investigation cannot be updated.']);
            }

            $investigation->update([
                ...$data,
                'status' => 'in_progress',
                'investigation_date' => $investigation->investigation_date ?? now(),
            ]);

            return $investigation->fresh();
        });
    }

    public function submit(LoanInvestigation $investigation, array $data, User $officer): LoanInvestigation
    {
        return DB::transaction(function () use ($investigation, $data, $officer): LoanInvestigation {
            $investigation = LoanInvestigation::query()->lockForUpdate()->find($investigation->id);

            if ($investigation->assigned_to !== $officer->id) {
                throw ValidationException::withMessages(['investigation' => 'Only the assigned investigator can submit this investigation.']);
            }

            if (! in_array($investigation->status, ['assigned', 'in_progress', 'returned'], true)) {
                throw ValidationException::withMessages(['investigation' => 'This investigation has already been submitted.']);
            }

            $application = $investigation->application;
            if ($application->status !== 'under_investigation') {
                throw ValidationException::withMessages(['application' => 'The application is not under investigation.']);
            }

            $investigation->update([
                ...$data,
                'status' => 'submitted',
                'submitted_at' => now(),
                'investigation_date' => $investigation->investigation_date ?? now(),
            ]);

            // RA-7: the application advances directly to admin decision.
            $application->update(['status' => 'pending_admin_decision']);

            return $investigation->fresh();
        });
    }
}
