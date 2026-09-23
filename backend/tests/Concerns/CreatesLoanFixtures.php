<?php

namespace Tests\Concerns;

use App\Models\CommitteeMeeting;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\LoanApplication;
use App\Models\LoanEligibilityDecision;
use App\Models\LoanGuarantor;
use App\Models\LoanInvestigation;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Services\FinancialCoreService;
use App\Services\LoanApplicationService;
use App\Services\LoanDecisionService;
use App\Services\LoanEligibilityService;
use App\Services\LoanGuarantorService;
use App\Services\LoanInvestigationService;
use Illuminate\Support\Str;
use PDO;

trait CreatesLoanFixtures
{
    private function loanMember(string $status = 'active', ?int $joinedMonthsAgo = null): Member
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::where('name', 'member')->firstOrFail());

        return Member::create([
            'user_id' => $user->id,
            'member_number' => 'MEM-'.Str::upper(Str::random(10)),
            'joined_at' => $joinedMonthsAgo !== null ? now()->subMonths($joinedMonthsAgo) : now()->subMonths(6),
            'status' => $status,
            'membership_type' => 'regular',
        ]);
    }

    private function loanMemberJoinedAt(\DateTimeInterface $joinedAt, string $status = 'active'): Member
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::where('name', 'member')->firstOrFail());

        return Member::create([
            'user_id' => $user->id,
            'member_number' => 'MEM-'.Str::upper(Str::random(10)),
            'joined_at' => $joinedAt,
            'status' => $status,
            'membership_type' => 'regular',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());

        return $user;
    }

    private function loanProduct(array $overrides = []): LoanProduct
    {
        return LoanProduct::factory()->create($overrides);
    }

    private function committeeMeeting(int $inDays = 15, int $cutoffDays = 14): CommitteeMeeting
    {
        return CommitteeMeeting::create([
            'meeting_date' => now()->addDays($inDays),
            'meeting_type' => 'loan_committee',
            'cutoff_days' => $cutoffDays,
            'status' => 'scheduled',
            'created_by' => $this->userWithRole('admin')->id,
        ]);
    }

    private function seedBalance(Member $member, string $accountType, int $amountMinor): FinancialAccount
    {
        $service = app(FinancialCoreService::class);
        $account = $member->financialAccounts()->where('account_type', $accountType)->first();

        if (! $account instanceof FinancialAccount) {
            $account = $service->createAccount($member, $accountType);
        }

        FinancialTransaction::create([
            'account_id' => $account->id,
            'transaction_type' => $accountType,
            'direction' => 'credit',
            'amount_minor' => $amountMinor,
            'transaction_date' => now(),
            'reference' => 'BAL-'.Str::upper(Str::random(10)),
            'status' => 'posted',
            'created_by' => $member->user_id,
            'posted_by' => $member->user_id,
            'posted_at' => now(),
        ]);

        return $account;
    }

    private function draftApplication(Member $member, LoanProduct $product, int $amountMinor = 500000): LoanApplication
    {
        return app(LoanApplicationService::class)->create($member, [
            'loan_product_id' => $product->id,
            'amount_requested_minor' => $amountMinor,
            'purpose' => 'Salary advance',
        ]);
    }

    private function submitApplication(LoanApplication $application): LoanApplication
    {
        $this->committeeMeeting();
        $this->eligibleDecision($application->member, $application->product);

        return app(LoanApplicationService::class)->submit($application);
    }

    /**
     * Record an administrative eligibility decision for the member + product
     * pair. Defaults to an eligible decision so application-flow fixtures can
     * proceed through submission under the admin-authority model.
     */
    private function eligibleDecision(
        Member $member,
        LoanProduct $product,
        string $status = 'eligible',
        ?string $reason = null,
    ): LoanEligibilityDecision {
        $admin = $this->userWithRole('admin');

        return app(LoanEligibilityService::class)->recordDecision($member, $product, $admin, $status, $reason);
    }

    private function acceptedApplication(Member $member, LoanProduct $product, int $guarantorCount): LoanApplication
    {
        $application = $this->submitApplication($this->draftApplication($member, $product));

        for ($index = 0; $index < $guarantorCount; $index++) {
            $guarantorMember = $this->loanMember();
            $request = app(LoanGuarantorService::class)->request($application, $member, $guarantorMember->id);
            $this->acceptGuarantor($request);
        }

        return $application->fresh();
    }

    private function acceptGuarantor(LoanGuarantor $request): void
    {
        app(LoanGuarantorService::class)->accept($request, $request->guarantorMember->user);
    }

    private function assignedInvestigation(LoanApplication $application, User $officer): LoanInvestigation
    {
        return app(LoanInvestigationService::class)->assign($application, $officer->id);
    }

    private function submittedInvestigation(LoanApplication $application, User $officer): LoanInvestigation
    {
        $investigation = $this->assignedInvestigation($application, $officer);

        return app(LoanInvestigationService::class)->submit($investigation, ['recommendation' => 'Recommend approval based on findings.'], $officer);
    }

    private function approvalTerms(array $overrides = []): array
    {
        return array_merge([
            'approved_amount_minor' => 500000,
            'interest_rate_basis_points' => 1000,
            'interest_method' => 'reducing_balance',
            'repayment_months' => 11,
            'decision_reason' => 'Application meets all lending criteria.',
        ], $overrides);
    }

    private function pendingDecisionApplication(Member $member, LoanProduct $product, User $officer): LoanApplication
    {
        $application = $this->acceptedApplication($member, $product, $product->required_guarantors);
        $this->submittedInvestigation($application, $officer);

        return $application->fresh();
    }

    private function approveApplication(LoanApplication $application, User $admin, array $overrides = [])
    {
        return app(LoanDecisionService::class)->approve($application, $admin, $this->approvalTerms($overrides));
    }

    private function secondConnection(): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            getenv('DB_TEST_HOST'),
            getenv('DB_TEST_PORT'),
            getenv('DB_TEST_DATABASE')
        );

        $connection = new PDO($dsn, getenv('DB_TEST_USERNAME'), getenv('DB_TEST_PASSWORD'));
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $connection;
    }
}
