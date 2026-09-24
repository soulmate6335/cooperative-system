<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanGuarantor;
use App\Models\Member;
use App\Models\User;
use App\Notifications\GuarantorRequestAccepted;
use App\Notifications\GuarantorRequestDeclined;
use App\Notifications\GuarantorRequestReceived;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanGuarantorService
{
    /**
     * RA-1: an application counts as an active guarantee obligation while it
     * remains in any of these states.
     */
    public const OPEN_APPLICATION_STATUSES = [
        'guarantors_confirmed', 'under_investigation', 'committee_reviewed',
        'pending_admin_decision', 'approved',
    ];

    /**
     * RA-1: a resulting loan counts as an active guarantee obligation while it
     * remains in any of these states.
     */
    public const ACTIVE_LOAN_STATUSES = ['pending_disbursement', 'active', 'overdue', 'defaulted'];

    private const REQUESTABLE_STATES = ['draft', 'submitted', 'awaiting_guarantors'];

    private const RESPONSE_STATES = ['submitted', 'awaiting_guarantors', 'guarantors_confirmed'];

    public function hasGuaranteeExposure(Member $guarantor): bool
    {
        return LoanGuarantor::query()
            ->where('guarantor_member_id', $guarantor->id)
            ->where('status', 'accepted')
            ->where(function ($query): void {
                $query->whereHas('application', fn ($application) => $application->whereIn('status', self::OPEN_APPLICATION_STATUSES))
                    ->orWhereHas('application.loan', fn ($loan) => $loan->whereIn('status', self::ACTIVE_LOAN_STATUSES));
            })
            ->exists();
    }

    /**
     * Candidate pool for the applicant's guarantor picker. Reuses the same
     * server-side eligibility rules as request(): active members, excluding
     * the applicant, members already requested on this application and
     * members carrying an active guarantee obligation elsewhere.
     */
    public function candidates(LoanApplication $application, ?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        if (! in_array($application->status, self::REQUESTABLE_STATES, true)) {
            throw ValidationException::withMessages(['application' => 'Guarantor requests are not allowed while the application is in this state.']);
        }

        return Member::query()
            ->with('user')
            ->where('status', 'active')
            ->where('id', '!=', $application->member_id)
            ->whereDoesntHave('loanGuarantees', fn ($query) => $query->where('loan_application_id', $application->id))
            ->whereDoesntHave('loanGuarantees', function ($query): void {
                $query->where('status', 'accepted')
                    ->where(function ($exposure): void {
                        $exposure->whereHas('application', fn ($application) => $application->whereIn('status', self::OPEN_APPLICATION_STATUSES))
                            ->orWhereHas('application.loan', fn ($loan) => $loan->whereIn('status', self::ACTIVE_LOAN_STATUSES));
                    });
            })
            ->when($search !== null && $search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('member_number', 'ilike', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'ilike', "%{$search}%"));
            }))
            ->orderBy('created_at')
            ->paginate($perPage);
    }

    public function request(LoanApplication $application, Member $applicant, string $guarantorMemberId): LoanGuarantor
    {
        return DB::transaction(function () use ($application, $applicant, $guarantorMemberId): LoanGuarantor {
            $application = LoanApplication::query()->lockForUpdate()->find($application->id);

            if ($application->member_id !== $applicant->id) {
                throw ValidationException::withMessages(['application' => 'Guarantors can only be requested on your own application.']);
            }

            if ($application->member->status !== 'active') {
                throw ValidationException::withMessages(['member' => 'Member-driven actions are frozen until the membership is reactivated.']);
            }

            if (! in_array($application->status, self::REQUESTABLE_STATES, true)) {
                throw ValidationException::withMessages(['application' => 'Guarantor requests are not allowed while the application is in this state.']);
            }

            if ($guarantorMemberId === $application->member_id) {
                throw ValidationException::withMessages(['guarantor_member_id' => 'A member cannot guarantee their own loan.']);
            }

            // Locking the guarantor member row serializes concurrent requests
            // and acceptances for that member so exposure cannot be bypassed.
            $guarantor = Member::query()->lockForUpdate()->find($guarantorMemberId);

            if (! $guarantor instanceof Member || $guarantor->status !== 'active') {
                throw ValidationException::withMessages(['guarantor_member_id' => 'The guarantor must be an active member.']);
            }

            if ($application->guarantors()->where('guarantor_member_id', $guarantor->id)->exists()) {
                throw ValidationException::withMessages(['guarantor_member_id' => 'This member has already been requested as a guarantor.']);
            }

            if ($this->hasGuaranteeExposure($guarantor)) {
                throw ValidationException::withMessages(['guarantor_member_id' => 'The member already has an active guarantee obligation.']);
            }

            $request = $application->guarantors()->create([
                'guarantor_member_id' => $guarantor->id,
                'status' => 'pending',
                'requested_at' => now(),
            ]);

            $this->recalculateStage($application->fresh());

            // New guarantor request event notification for the guarantor member.
            $guarantor->user->notify(new GuarantorRequestReceived($application->application_number, $application->id));

            return $request->fresh();
        });
    }

    public function accept(LoanGuarantor $guarantorRequest, User $user, ?string $note = null): LoanGuarantor
    {
        return DB::transaction(function () use ($guarantorRequest, $user, $note): LoanGuarantor {
            $request = LoanGuarantor::query()->lockForUpdate()->find($guarantorRequest->id);

            // D-7: the application is locked and the applicant's membership is
            // revalidated so a suspended applicant freezes the workflow.
            $application = LoanApplication::query()->lockForUpdate()->find($request->loan_application_id);

            $guarantor = $request->guarantorMember;

            if ($guarantor->user_id !== $user->id) {
                throw ValidationException::withMessages(['guarantor' => 'You cannot respond to this guarantee request.']);
            }

            if ($application->member->status !== 'active') {
                throw ValidationException::withMessages(['member' => 'Member-driven actions are frozen until the membership is reactivated.']);
            }

            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['guarantor' => 'Only pending requests can be accepted.']);
            }

            if (! in_array($application->status, self::RESPONSE_STATES, true)) {
                throw ValidationException::withMessages(['guarantor' => 'This application is no longer accepting guarantor responses.']);
            }

            // RA-10: active status is revalidated at the moment of acceptance.
            $guarantor = Member::query()->lockForUpdate()->find($guarantor->id);

            if (! $guarantor instanceof Member || $guarantor->status !== 'active') {
                throw ValidationException::withMessages(['guarantor' => 'The guarantor must still be an active member to accept.']);
            }

            if ($this->hasGuaranteeExposure($guarantor)) {
                throw ValidationException::withMessages(['guarantor' => 'The member already has an active guarantee obligation.']);
            }

            $request->update([
                'status' => 'accepted',
                'responded_at' => now(),
                'responded_by' => $user->id,
                'response_note' => $note,
            ]);

            $this->recalculateStage($application->fresh());

            // Guarantor acceptance event notification for the applicant member.
            $application->member->user->notify(new GuarantorRequestAccepted($application->application_number, (string) $guarantor->user->name));

            return $request->fresh();
        });
    }

    public function decline(LoanGuarantor $guarantorRequest, User $user, ?string $note = null): LoanGuarantor
    {
        return DB::transaction(function () use ($guarantorRequest, $user, $note): LoanGuarantor {
            $request = LoanGuarantor::query()->lockForUpdate()->find($guarantorRequest->id);

            // D-7: the application is locked and the applicant's membership is
            // revalidated so a suspended applicant's workflow cannot be changed
            // by a guarantor response.
            $application = LoanApplication::query()->lockForUpdate()->find($request->loan_application_id);

            $guarantor = $request->guarantorMember;

            if ($guarantor->user_id !== $user->id) {
                throw ValidationException::withMessages(['guarantor' => 'You cannot respond to this guarantee request.']);
            }

            if ($application->member->status !== 'active') {
                throw ValidationException::withMessages(['member' => 'Member-driven actions are frozen until the membership is reactivated.']);
            }

            // RA-2: a guarantor may back out while the guarantee stage is still
            // open (pending or already accepted) before the application moves
            // into investigation. The request history is preserved as declined.
            if (! in_array($request->status, ['pending', 'accepted'], true)) {
                throw ValidationException::withMessages(['guarantor' => 'Only open guarantee requests can be declined.']);
            }

            if (in_array($application->status, ['under_investigation', 'pending_admin_decision', 'approved', 'rejected', 'cancelled'], true)) {
                throw ValidationException::withMessages(['guarantor' => 'This application is no longer accepting guarantor changes.']);
            }

            $request->update([
                'status' => 'declined',
                'responded_at' => now(),
                'responded_by' => $user->id,
                'response_note' => $note ?? 'Declined by the guarantor.',
            ]);

            // RA-2: a decline after confirmation reverts the application to
            // awaiting_guarantors so a replacement can be requested.
            $this->recalculateStage($application->fresh());

            // Guarantor decline event notification for the applicant member.
            $application->member->user->notify(new GuarantorRequestDeclined($application->application_number, (string) $guarantor->user->name));

            return $request->fresh();
        });
    }

    public function cancelPendingRequest(LoanGuarantor $guarantorRequest, Member $applicant): LoanGuarantor
    {
        return DB::transaction(function () use ($guarantorRequest, $applicant): LoanGuarantor {
            $request = LoanGuarantor::query()->lockForUpdate()->find($guarantorRequest->id);

            if ($request->application->member_id !== $applicant->id) {
                throw ValidationException::withMessages(['application' => 'You can only cancel requests on your own application.']);
            }

            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['guarantor' => 'Only pending requests can be cancelled.']);
            }

            $request->update(['status' => 'cancelled']);
            $this->recalculateStage($request->application->fresh());

            return $request->fresh();
        });
    }

    private function recalculateStage(LoanApplication $application): void
    {
        if (! in_array($application->status, ['submitted', 'awaiting_guarantors', 'guarantors_confirmed'], true)) {
            return;
        }

        $accepted = $application->acceptedGuarantorCount();
        $required = $application->product->required_guarantors;

        if ($accepted >= $required) {
            if ($application->status !== 'guarantors_confirmed') {
                $application->update(['status' => 'guarantors_confirmed']);
            }
        } elseif ($application->guarantors()->whereIn('status', ['pending', 'accepted', 'declined'])->exists()) {
            if ($application->status !== 'awaiting_guarantors') {
                $application->update(['status' => 'awaiting_guarantors']);
            }
        } elseif ($application->status !== 'submitted') {
            $application->update(['status' => 'submitted']);
        }
    }
}
