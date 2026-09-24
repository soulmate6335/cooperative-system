<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\Role;
use App\Models\User;
use App\Notifications\MembershipApplicationApproved;
use App\Notifications\MembershipApplicationRejected;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipApplicationService
{
    public function submit(array $data): MemberApplication
    {
        return DB::transaction(function () use ($data): MemberApplication {
            /** @var UploadedFile $profilePhoto */
            $profilePhoto = $data['profile_photo'];
            $profilePhotoPath = $profilePhoto->store('member-applications', 'local');

            $user = User::create([
                'name' => $data['full_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => 'pending',
            ]);

            unset($data['password'], $data['profile_photo']);

            return $user->memberApplications()->create([
                ...$data,
                'profile_photo' => $profilePhotoPath,
                'application_number' => $this->uniqueApplicationNumber(),
                'status' => 'pending',
                'submitted_at' => now(),
            ]);
        });
    }

    public function approve(MemberApplication $application, User $reviewer): Member
    {
        return DB::transaction(function () use ($application, $reviewer): Member {
            $applicationId = $application->id;
            $application = MemberApplication::query()->lockForUpdate()->find($applicationId);

            if (! $application) {
                throw (new ModelNotFoundException)->setModel(MemberApplication::class, [$applicationId]);
            }

            if ($application->status !== 'pending') {
                throw ValidationException::withMessages(['application' => 'Only pending applications can be approved.']);
            }

            if ($application->user()->whereHas('member')->exists()) {
                throw ValidationException::withMessages(['application' => 'This user already has an active member profile.']);
            }

            $member = Member::create([
                'user_id' => $application->user_id,
                'member_number' => $this->uniqueMemberNumber(),
                'profile_photo' => $application->profile_photo,
                'joined_at' => now(),
                'status' => 'active',
                'membership_type' => 'regular',
                'approved_by' => $reviewer->id,
                'approved_at' => now(),
            ]);

            $application->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'rejection_reason' => null,
            ]);
            $application->user()->update(['status' => 'active']);
            $application->user->roles()->syncWithoutDetaching([
                Role::where('name', 'member')->firstOrFail()->id,
            ]);

            // Membership approval event notification for the applicant.
            $application->user->notify(new MembershipApplicationApproved($application->application_number));

            return $member;
        });
    }

    public function reject(MemberApplication $application, User $reviewer, string $reason): MemberApplication
    {
        return DB::transaction(function () use ($application, $reviewer, $reason): MemberApplication {
            $applicationId = $application->id;
            $application = MemberApplication::query()->lockForUpdate()->find($applicationId);

            if (! $application) {
                throw (new ModelNotFoundException)->setModel(MemberApplication::class, [$applicationId]);
            }

            if ($application->status !== 'pending') {
                throw ValidationException::withMessages(['application' => 'Only pending applications can be rejected.']);
            }

            $application->update([
                'status' => 'rejected',
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'rejection_reason' => $reason,
            ]);
            $application->user()->update(['status' => 'suspended']);

            // Membership rejection event notification for the applicant,
            // including the reviewer-provided reason when one was recorded.
            $application->user->notify(new MembershipApplicationRejected($application->application_number, $reason));

            return $application->fresh(['reviewer']);
        });
    }

    private function uniqueApplicationNumber(): string
    {
        do {
            $number = 'APP-'.strtoupper(Str::random(10));
        } while (MemberApplication::where('application_number', $number)->exists());

        return $number;
    }

    private function uniqueMemberNumber(): string
    {
        do {
            $number = 'MEM-'.strtoupper(Str::random(10));
        } while (Member::where('member_number', $number)->exists());

        return $number;
    }
}
