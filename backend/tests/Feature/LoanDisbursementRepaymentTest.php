<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\LoanInstallment;
use App\Models\LoanProduct;
use App\Models\LoanRepaymentAllocation;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\FinancialCoreService;
use App\Services\LoanDecisionService;
use App\Services\LoanDisbursementService;
use App\Services\LoanRepaymentScheduleService;
use App\Services\LoanRepaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Concerns\CreatesLoanFixtures;
use Tests\TestCase;

/**
 * Stage 5 - loan disbursement & repayment.
 *
 * Covers: disbursement queue/detail/authorization, the single full
 * disbursement with Financial Core posting, deterministic flat-interset
 * schedule generation, repayment submit -> verify -> allocate (oldest first,
 * interest before principal), completion derivation, reversal/unapply, RBAC
 * separation of duties, and financial-integrity invariants.
 */
class LoanDisbursementRepaymentTest extends TestCase
{
    use CreatesLoanFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ------------------------------------------------------------ helpers

    private function flatProduct(array $overrides = []): LoanProduct
    {
        return $this->loanProduct(array_merge([
            'interest_rate_basis_points' => 1000,
            'interest_method' => 'flat',
            'repayment_months' => 6,
            'minimum_amount_minor' => 10000,
            'maximum_amount_minor' => 10000000,
            'required_guarantors' => 2,
        ], $overrides));
    }

    private function approvedFlatLoan(User $admin, array $overrides = []): Loan
    {
        $member = $this->loanMember();
        $product = $this->flatProduct();
        $officer = $this->userWithRole('committee_officer');
        $application = $this->pendingDecisionApplication($member, $product, $officer);

        return $this->approveApplication($application, $admin, array_merge([
            'interest_method' => 'flat',
            'repayment_months' => 6,
        ], $overrides));
    }

    /**
     * @return array{loan: Loan, disbursement: LoanDisbursement, transaction: FinancialTransaction}
     */
    private function disbursedLoan(User $admin, array $overrides = []): array
    {
        $loan = $this->approvedFlatLoan($admin, $overrides);

        return app(LoanDisbursementService::class)->disburse($loan, $admin, [
            'amount_minor' => (int) $loan->principal_amount_minor,
        ]);
    }

    private function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::create([
            'code' => 'cash-'.Str::lower(Str::random(6)),
            'name' => 'Cash',
            'is_active' => true,
        ]);
    }

    private function submitRepayment(Loan $loan, User $recorder, int $amount, ?string $reference = null): Payment
    {
        return app(LoanRepaymentService::class)->submit([
            'member_id' => $loan->member_id,
            'loan_id' => $loan->id,
            'payment_method_id' => $this->paymentMethod()->id,
            'amount_minor' => $amount,
            'payment_date' => now(),
            'reference_number' => $reference ?? 'REP-'.strtoupper(Str::random(10)),
        ], $recorder);
    }

    private function verifyRepayment(Payment $payment): FinancialTransaction
    {
        return app(LoanRepaymentService::class)->verify($payment, $this->userWithRole('finance_officer'));
    }

    private function admin(): User
    {
        return $this->userWithRole('admin');
    }

    // --------------------------------------------------- disbursement queue

    public function test_authorized_finance_and_admin_can_view_disbursement_queue(): void
    {
        $loan = $this->approvedFlatLoan($this->admin());

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/loans/disbursement-queue')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $loan->id)
            ->assertJsonPath('data.0.status', 'pending_disbursement')
            ->assertJsonPath('data.0.member.name', $loan->member->user->name)
            ->assertJsonPath('data.0.product.name', $loan->application->product->name)
            ->assertJsonPath('data.0.decision.decision', 'approved');

        $this->actingAs($this->userWithRole('finance_officer'), 'sanctum')
            ->getJson('/api/v1/admin/loans/disbursement-queue')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_member_cannot_view_disbursement_queue(): void
    {
        $this->approvedFlatLoan($this->admin());

        $this->actingAs($this->userWithRole('member'), 'sanctum')
            ->getJson('/api/v1/admin/loans/disbursement-queue')
            ->assertForbidden();
    }

    public function test_committee_officer_cannot_view_disbursement_queue(): void
    {
        $this->approvedFlatLoan($this->admin());

        $this->actingAs($this->userWithRole('committee_officer'), 'sanctum')
            ->getJson('/api/v1/admin/loans/disbursement-queue')
            ->assertForbidden();
    }

    public function test_disbursed_loan_leaves_the_queue(): void
    {
        $admin = $this->admin();
        $this->approvedFlatLoan($admin); // still pending
        $result = $this->disbursedLoan($admin);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/loans/disbursement-queue')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/loans/'.$result['loan']->id.'/disbursement')
            ->assertOk()
            ->assertJsonPath('data.status', 'disbursed')
            ->assertJsonPath('data.disbursement.amount_minor', 500000)
            ->assertJsonCount(6, 'data.installments');
    }

    // ---------------------------------------------------- disbursement rules

    public function test_unapproved_loan_cannot_be_disbursed(): void
    {
        $admin = $this->admin();
        $draft = $this->draftApplication($this->loanMember(), $this->flatProduct());

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/loans/'.$draft->id.'/disburse', ['amount_minor' => 500000])
            ->assertNotFound();
    }

    public function test_rejected_loan_cannot_be_disbursed(): void
    {
        $admin = $this->admin();
        $application = $this->pendingDecisionApplication($this->loanMember(), $this->flatProduct(), $this->userWithRole('committee_officer'));
        app(LoanDecisionService::class)->reject($application, $admin, 'Incomplete supporting documentation.');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/loans/'.$application->id.'/disburse', ['amount_minor' => 500000])
            ->assertNotFound();
    }

    public function test_suspended_member_cannot_be_disbursed(): void
    {
        $admin = $this->admin();
        $loan = $this->approvedFlatLoan($admin);
        $loan->member->update(['status' => 'suspended']);

        try {
            app(LoanDisbursementService::class)->disburse($loan, $admin, ['amount_minor' => 500000]);
            $this->fail('A suspended member cannot have a loan disbursed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('member', $exception->errors());
        }

        $this->assertDatabaseCount('loan_disbursements', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_approved_loan_can_be_disbursed(): void
    {
        $admin = $this->admin();
        $loan = $this->approvedFlatLoan($admin);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/loans/'.$loan->id.'/disburse', ['amount_minor' => 500000])
            ->assertOk()
            ->assertJsonPath('data.loan.status', 'disbursed')
            ->assertJsonPath('data.disbursement.amount_minor', 500000)
            ->assertJsonPath('data.transaction.transaction_type', 'loan_disbursement');
    }

    public function test_disbursement_amount_cannot_exceed_approved_amount(): void
    {
        $admin = $this->admin();
        $loan = $this->approvedFlatLoan($admin);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/loans/'.$loan->id.'/disburse', ['amount_minor' => 600000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount_minor');

        $this->assertDatabaseCount('loan_disbursements', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_partial_disbursement_is_not_allowed(): void
    {
        $admin = $this->admin();
        $loan = $this->approvedFlatLoan($admin);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/loans/'.$loan->id.'/disburse', ['amount_minor' => 400000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount_minor');
    }

    public function test_duplicate_disbursement_is_rejected(): void
    {
        $admin = $this->admin();
        $loan = $this->approvedFlatLoan($admin);

        app(LoanDisbursementService::class)->disburse($loan, $admin, ['amount_minor' => 500000]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/loans/'.$loan->id.'/disburse', ['amount_minor' => 500000])
            ->assertUnprocessable();

        $this->assertDatabaseCount('loan_disbursements', 1);
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertSame('disbursed', $loan->fresh()->status);
    }

    public function test_disbursement_creates_a_single_disbursement_record(): void
    {
        $admin = $this->admin();
        $result = $this->disbursedLoan($admin);

        $this->assertDatabaseCount('loan_disbursements', 1);
        $this->assertDatabaseHas('loan_disbursements', [
            'id' => $result['disbursement']->id,
            'loan_id' => $result['loan']->id,
            'amount_minor' => 500000,
            'status' => 'posted',
            'authorized_by' => $admin->id,
        ]);
    }

    public function test_disbursement_creates_the_correct_financial_transaction(): void
    {
        $admin = $this->admin();
        $result = $this->disbursedLoan($admin);
        $loan = $result['loan'];

        $account = $loan->member->financialAccounts()->where('account_type', 'loan')->firstOrFail();

        $this->assertDatabaseHas('financial_transactions', [
            'id' => $result['transaction']->id,
            'account_id' => $account->id,
            'transaction_type' => 'loan_disbursement',
            'direction' => 'debit',
            'amount_minor' => 500000,
            'reference' => $loan->loan_number,
            'status' => 'posted',
        ]);
    }

    public function test_disbursement_posts_no_duplicate_financial_transaction(): void
    {
        $this->disbursedLoan($this->admin());

        $this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_loan_state_changes_after_disbursement(): void
    {
        $admin = $this->admin();
        $result = $this->disbursedLoan($admin);
        $loan = $result['loan']->fresh();

        $this->assertSame('disbursed', $loan->status);
        $this->assertSame(500000, (int) $loan->disbursed_amount_minor);
        $this->assertNotNull($loan->disbursed_at);
        $this->assertNotNull($loan->start_date);
        $this->assertNotNull($loan->maturity_date);
        $this->assertSame($loan->installments()->orderByDesc('installment_number')->first()->due_date->format('Y-m-d'), $loan->maturity_date->format('Y-m-d'));
        $this->assertSame(50000, (int) $loan->interest_amount_minor);
        $this->assertSame(550000, (int) $loan->total_payable_minor);
    }

    public function test_disbursement_is_atomic_when_schedule_cannot_be_generated(): void
    {
        $admin = $this->admin();
        $loan = $this->approvedFlatLoan($admin, ['interest_method' => 'reducing_balance']);

        try {
            app(LoanDisbursementService::class)->disburse($loan, $admin, ['amount_minor' => 500000]);
            $this->fail('Reducing-balance disbursement must fail while the formula is undefined.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('interest_method', $exception->errors());
        }

        $this->assertDatabaseCount('loan_disbursements', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('loan_installments', 0);
        $this->assertSame('pending_disbursement', $loan->fresh()->status);
        $this->assertSame(0, $loan->member->financialAccounts()->where('account_type', 'loan')->count());
    }

    // -------------------------------------------------------------- schedule

    public function test_schedule_is_generated_after_valid_disbursement(): void
    {
        $result = $this->disbursedLoan($this->admin());

        $this->assertDatabaseCount('loan_installments', 6);
        $this->assertSame(6, $result['installments']->count());
    }

    public function test_installment_count_matches_repayment_months(): void
    {
        $this->disbursedLoan($this->admin());

        $this->assertDatabaseCount('loan_installments', 6);
    }

    public function test_principal_sums_exactly_to_approved_principal(): void
    {
        $result = $this->disbursedLoan($this->admin());

        $this->assertSame(500000, (int) $result['loan']->installments()->sum('principal_due_minor'));
    }

    public function test_interest_sums_exactly_to_calculated_interest(): void
    {
        $result = $this->disbursedLoan($this->admin());

        $this->assertSame(50000, (int) $result['loan']->installments()->sum('interest_due_minor'));
    }

    public function test_total_installments_sum_correctly(): void
    {
        $result = $this->disbursedLoan($this->admin());

        $sum = (int) $result['loan']->installments()->sum('total_due_minor');
        $this->assertSame(550000, $sum);
        $this->assertSame($sum, (int) $result['loan']->fresh()->total_payable_minor);
    }

    public function test_rounding_does_not_create_or_lose_money(): void
    {
        $admin = $this->admin();
        $member = $this->loanMember();
        $product = $this->flatProduct();
        $officer = $this->userWithRole('committee_officer');
        $application = $this->pendingDecisionApplication($member, $product, $officer);

        // Approved amount 250001 minor is not divisible across 6 installments.
        $rounded = $this->approveApplication($application, $admin, [
            'approved_amount_minor' => 250001,
            'interest_method' => 'flat',
            'repayment_months' => 6,
        ]);
        $roundedResult = app(LoanDisbursementService::class)->disburse($rounded, $admin, ['amount_minor' => 250001]);

        // interest = floor(250001 * 1000 / 10000) = floor(25000.1) = 25000
        $this->assertSame(25000, (int) $roundedResult['loan']->fresh()->interest_amount_minor);
        $this->assertSame(250001, (int) $roundedResult['loan']->fresh()->installments()->sum('principal_due_minor'));
        $this->assertSame(25000, (int) $roundedResult['loan']->fresh()->installments()->sum('interest_due_minor'));
        $this->assertSame(275001, (int) $roundedResult['loan']->fresh()->installments()->sum('total_due_minor'));
    }

    public function test_duplicate_schedule_generation_is_prevented(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        try {
            app(LoanRepaymentScheduleService::class)->generate($loan);
            $this->fail('A second schedule must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('loan', $exception->errors());
        }

        $this->assertDatabaseCount('loan_installments', 6);
    }

    public function test_schedule_uses_the_loan_snapshot_not_current_product_terms(): void
    {
        $admin = $this->admin();
        $member = $this->loanMember();
        $product = $this->flatProduct();
        $application = $this->pendingDecisionApplication($member, $product, $this->userWithRole('committee_officer'));
        $loan = $this->approveApplication($application, $admin, [
            'interest_method' => 'flat',
            'repayment_months' => 6,
        ]);

        // Product terms change after approval: the loan snapshot must win.
        $product->update(['interest_rate_basis_points' => 5000, 'repayment_months' => 24]);
        $result = app(LoanDisbursementService::class)->disburse($loan, $admin, ['amount_minor' => 500000]);

        $this->assertSame(50000, (int) $loan->fresh()->interest_amount_minor);
        $this->assertSame(6, $result['installments']->count());
    }

    // ------------------------------------------------------------ repayments

    public function test_member_can_submit_repayment_for_own_loan(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $this->actingAs($loan->member->user, 'sanctum')
            ->postJson('/api/v1/member/loans/'.$loan->id.'/repayments', [
                'amount_minor' => 10000,
                'payment_method_id' => $this->paymentMethod()->id,
                'payment_date' => now()->toISOString(),
                'reference_number' => 'REP-HTTP-UNIQUE',
            ])
            ->assertCreated()
            ->assertJsonPath('data.purpose', 'loan_repayment')
            ->assertJsonPath('data.loan_id', $loan->id)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_member_cannot_repay_another_members_loan(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];
        $other = $this->loanMember();

        $this->actingAs($other->user, 'sanctum')
            ->postJson('/api/v1/member/loans/'.$loan->id.'/repayments', [
                'amount_minor' => 10000,
                'payment_method_id' => $this->paymentMethod()->id,
                'payment_date' => now()->toISOString(),
                'reference_number' => 'REP-NOT-OWN',
            ])
            ->assertForbidden();
    }

    public function test_member_cannot_view_or_schedule_another_members_loan(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];
        $other = $this->loanMember();

        $this->actingAs($other->user, 'sanctum')
            ->getJson('/api/v1/member/loans/'.$loan->id)
            ->assertForbidden();

        $this->actingAs($other->user, 'sanctum')
            ->getJson('/api/v1/member/loans/'.$loan->id.'/schedule')
            ->assertForbidden();
    }

    public function test_repayment_rejects_invalid_payment_method(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $this->actingAs($loan->member->user, 'sanctum')
            ->postJson('/api/v1/member/loans/'.$loan->id.'/repayments', [
                'amount_minor' => 10000,
                'payment_method_id' => (string) Str::uuid(),
                'payment_date' => now()->toISOString(),
                'reference_number' => 'REP-BAD-METHOD',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method_id');
    }

    public function test_repayment_amount_must_be_positive(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $this->actingAs($loan->member->user, 'sanctum')
            ->postJson('/api/v1/member/loans/'.$loan->id.'/repayments', [
                'amount_minor' => 0,
                'payment_method_id' => $this->paymentMethod()->id,
                'payment_date' => now()->toISOString(),
                'reference_number' => 'REP-ZERO',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount_minor');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_pending_payment_does_not_affect_posted_state(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $this->submitRepayment($loan, $loan->member->user, 10000);

        // Only the disbursement transaction exists; no allocation is derived
        // from an unverified payment; the ledger balance is untouched.
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseCount('loan_repayment_allocations', 0);
        $account = $loan->member->financialAccounts()->where('account_type', 'loan')->firstOrFail();
        $this->assertSame(-500000, app(FinancialCoreService::class)->balance($account));
        $this->assertSame(0, $loan->fresh()->obligationSummary()['total_paid_minor']);
        $this->assertSame(LoanInstallment::STATUS_PENDING, $loan->fresh()->installments()->first()->status);
    }

    public function test_verified_repayment_posts_credit_and_allocates(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $payment = $this->submitRepayment($loan, $loan->member->user, 10000);
        $transaction = $this->verifyRepayment($payment);

        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertDatabaseHas('financial_transactions', [
            'id' => $transaction->id,
            'transaction_type' => 'loan_repayment',
            'direction' => 'credit',
            'amount_minor' => 10000,
            'status' => 'posted',
        ]);
        $this->assertDatabaseCount('loan_repayment_allocations', 1);
        $this->assertSame(10000, $loan->fresh()->obligationSummary()['total_paid_minor']);
    }

    public function test_repayment_allocates_to_oldest_outstanding_installment(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $payment = $this->submitRepayment($loan, $loan->member->user, 10000);
        $this->verifyRepayment($payment);

        $allocation = LoanRepaymentAllocation::firstOrFail();
        $this->assertSame(1, (int) $allocation->installment->installment_number);
        $this->assertSame(1, LoanRepaymentAllocation::count());
        // Only the first installment carried the payment.
        $this->assertSame(0, $loan->fresh()->installments()->where('installment_number', 2)->first()->allocations()->count());
    }

    public function test_interest_is_allocated_before_principal_within_installment(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $payment = $this->submitRepayment($loan, $loan->member->user, 10000);
        $this->verifyRepayment($payment);

        $allocation = LoanRepaymentAllocation::firstOrFail();
        $this->assertSame(8334, (int) $allocation->interest_allocated_minor);
        $this->assertSame(1666, (int) $allocation->principal_allocated_minor);
        $this->assertSame(10000, (int) $allocation->amount_minor);
    }

    public function test_partial_repayment_keeps_installment_pending(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $payment = $this->submitRepayment($loan, $loan->member->user, 30000);
        $this->verifyRepayment($payment);

        $installment = $loan->fresh()->installments()->where('installment_number', 1)->firstOrFail();
        $this->assertSame(LoanInstallment::STATUS_PENDING, $installment->status);
        $this->assertNull($installment->paid_at);
        $this->assertSame(30000 - 8334, $installment->paidPrincipalMinor());
        $this->assertSame(8334, $installment->paidInterestMinor());
        $this->assertSame(91668 - 30000, $installment->total_due_minor - $installment->paidPrincipalMinor() - $installment->paidInterestMinor());
    }

    public function test_fully_paid_installment_becomes_paid(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $payment = $this->submitRepayment($loan, $loan->member->user, 91668);
        $this->verifyRepayment($payment);

        $installment = $loan->fresh()->installments()->where('installment_number', 1)->firstOrFail();
        $this->assertSame(LoanInstallment::STATUS_PAID, $installment->status);
        $this->assertNotNull($installment->paid_at);
        $this->assertSame(91668, (int) $loan->fresh()->obligationSummary()['total_paid_minor']);
    }

    public function test_one_repayment_can_cover_multiple_installments(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $payment = $this->submitRepayment($loan, $loan->member->user, 210000);
        $this->verifyRepayment($payment);

        // i1 91668 + i2 91668 + 26664 against i3 (interest 8333 + principal 18331)
        $this->assertSame(3, LoanRepaymentAllocation::count());
        $installments = $loan->fresh()->installments()->orderBy('installment_number')->get();
        $this->assertSame(LoanInstallment::STATUS_PAID, $installments[0]->status);
        $this->assertSame(LoanInstallment::STATUS_PAID, $installments[1]->status);
        $this->assertSame(LoanInstallment::STATUS_PENDING, $installments[2]->status);
        $third = $installments[2]->allocations()->firstOrFail();
        $this->assertSame(8333, (int) $third->interest_allocated_minor);
        $this->assertSame(18331, (int) $third->principal_allocated_minor);
    }

    public function test_overpayment_is_rejected_at_submission(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        try {
            $this->submitRepayment($loan, $loan->member->user, 560000);
            $this->fail('Overpayment must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount_minor', $exception->errors());
        }

        $this->actingAs($loan->member->user, 'sanctum')
            ->postJson('/api/v1/member/loans/'.$loan->id.'/repayments', [
                'amount_minor' => 560000,
                'payment_method_id' => $this->paymentMethod()->id,
                'payment_date' => now()->toISOString(),
                'reference_number' => 'REP-OVERPAY',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_overpayment_is_rejected_at_verification_when_outstanding_shrank(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        // Both fit against the outstanding obligation at submission time...
        $paymentA = $this->submitRepayment($loan, $loan->member->user, 500000, 'REP-A');
        $paymentB = $this->submitRepayment($loan, $loan->member->user, 60000, 'REP-B');

        // ...but after A verifies only 50000 remains; B must fail atomically.
        $this->verifyRepayment($paymentA);

        try {
            $this->verifyRepayment($paymentB);
            $this->fail('Verification-time overpayment must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount_minor', $exception->errors());
        }

        $this->assertSame('pending', $paymentB->fresh()->status);
        $this->assertDatabaseCount('financial_transactions', 2); // disbursement + repayment A only
        $this->assertDatabaseCount('loan_repayment_allocations', 6);
    }

    public function test_finance_officer_can_record_repayment_on_behalf_of_member(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];
        $recorder = $this->userWithRole('finance_officer');

        $this->actingAs($recorder, 'sanctum')
            ->postJson('/api/v1/finance/payments', [
                'member_id' => $loan->member_id,
                'loan_id' => $loan->id,
                'payment_method_id' => $this->paymentMethod()->id,
                'amount_minor' => 20000,
                'payment_date' => now()->toISOString(),
                'reference_number' => 'REP-FINANCE',
                'purpose' => 'loan_repayment',
            ])
            ->assertCreated()
            ->assertJsonPath('data.purpose', 'loan_repayment');

        // A different finance officer verifies and the payment allocates.
        $verifier = $this->userWithRole('finance_officer');
        $payment = Payment::where('reference_number', 'REP-FINANCE')->firstOrFail();
        $transaction = app(LoanRepaymentService::class)->verify($payment, $verifier);

        $this->assertDatabaseHas('financial_transactions', [
            'id' => $transaction->id,
            'transaction_type' => 'loan_repayment',
            'direction' => 'credit',
            'amount_minor' => 20000,
        ]);
        $this->assertSame(20000, $loan->fresh()->obligationSummary()['total_paid_minor']);
    }

    public function test_member_can_list_and_view_own_loans(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $this->actingAs($loan->member->user, 'sanctum')
            ->getJson('/api/v1/member/loans')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $loan->id)
            ->assertJsonPath('data.0.obligations.total_obligation_minor', 550000)
            ->assertJsonPath('data.0.obligations.total_outstanding_minor', 550000)
            ->assertJsonCount(6, 'data.0.installments');
    }

    public function test_member_loan_detail_exposes_disbursement_and_schedule(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $this->actingAs($loan->member->user, 'sanctum')
            ->getJson('/api/v1/member/loans/'.$loan->id)
            ->assertOk()
            ->assertJsonPath('data.disbursement.amount_minor', 500000)
            ->assertJsonCount(6, 'data.installments')
            ->assertJsonPath('data.obligations.next_due_installment.installment_number', 1);

        $this->actingAs($loan->member->user, 'sanctum')
            ->getJson('/api/v1/member/loans/'.$loan->id.'/schedule')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.installment_number', 1)
            ->assertJsonPath('data.0.principal_due_minor', 83334)
            ->assertJsonPath('data.0.interest_due_minor', 8334);
    }

    // ------------------------------------------------------------ completion

    public function test_loan_remains_active_while_obligation_exists(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $payment = $this->submitRepayment($loan, $loan->member->user, 100000);
        $this->verifyRepayment($payment);

        $this->assertSame('disbursed', $loan->fresh()->status);
        $this->assertSame(450000, $loan->fresh()->obligationSummary()['total_outstanding_minor']);
    }

    public function test_loan_completes_when_all_obligations_are_satisfied(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $payment = $this->submitRepayment($loan, $loan->member->user, 550000, 'REP-FULL');
        $this->verifyRepayment($payment);

        $this->assertSame('completed', $loan->fresh()->status);
        $this->assertSame(0, $loan->fresh()->obligationSummary()['total_outstanding_minor']);
        $this->assertSame(6, $loan->fresh()->installments()->where('status', 'paid')->count());
    }

    public function test_pending_repayment_cannot_complete_a_loan(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $this->submitRepayment($loan, $loan->member->user, 550000, 'REP-FULL-PENDING');

        $this->assertSame('disbursed', $loan->fresh()->status);
    }

    // ------------------------------------------------------------- reversal

    public function test_posted_repayment_can_be_reversed_through_existing_mechanism(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $payment = $this->submitRepayment($loan, $loan->member->user, 30000);
        $transaction = $this->verifyRepayment($payment);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/finance/transactions/'.$transaction->id.'/reverse')
            ->assertOk()
            ->assertJsonPath('data.transaction_type', 'reversal')
            ->assertJsonPath('data.direction', 'debit')
            ->assertJsonPath('data.amount_minor', 30000);
    }

    public function test_original_transaction_remains_immutable_after_reversal(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $payment = $this->submitRepayment($loan, $loan->member->user, 30000);
        $transaction = $this->verifyRepayment($payment);

        $reversal = app(LoanRepaymentService::class)->reverse($transaction, $this->admin());

        $this->assertNotNull($reversal->reverses_transaction_id);
        $this->assertSame($transaction->id, $reversal->reverses_transaction_id);

        try {
            $transaction->update(['amount_minor' => 1]);
            $this->fail('The original posted transaction must be immutable.');
        } catch (LogicException $exception) {
            $this->assertTrue(true);
        }

        $this->assertSame(30000, (int) $transaction->fresh()->amount_minor);
    }

    public function test_reversal_voids_allocations_and_restores_installments(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $payment = $this->submitRepayment($loan, $loan->member->user, 30000);
        $transaction = $this->verifyRepayment($payment);

        $allocation = LoanRepaymentAllocation::where('status', 'posted')->firstOrFail();

        app(LoanRepaymentService::class)->reverse($transaction, $this->admin());

        $this->assertSame('voided', $allocation->fresh()->status);
        $this->assertNotNull($allocation->fresh()->voided_by_transaction_id);
        $this->assertSame(0, $loan->fresh()->obligationSummary()['total_paid_minor']);
        $installment = $loan->fresh()->installments()->where('installment_number', 1)->firstOrFail();
        $this->assertSame(LoanInstallment::STATUS_PENDING, $installment->status);
        $this->assertSame(0, $installment->paidPrincipalMinor());
    }

    public function test_loan_reopens_after_reversal_creates_outstanding_balance(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $payment = $this->submitRepayment($loan, $loan->member->user, 550000, 'REP-REOPEN');
        $transaction = $this->verifyRepayment($payment);

        $this->assertSame('completed', $loan->fresh()->status);

        app(LoanRepaymentService::class)->reverse($transaction, $this->admin());

        $this->assertSame('disbursed', $loan->fresh()->status);
        $this->assertSame(550000, $loan->fresh()->obligationSummary()['total_outstanding_minor']);
    }

    // -------------------------------------------------------------- security

    public function test_member_cannot_disburse(): void
    {
        $loan = $this->approvedFlatLoan($this->admin());

        $this->actingAs($this->userWithRole('member'), 'sanctum')
            ->postJson('/api/v1/admin/loans/'.$loan->id.'/disburse', ['amount_minor' => 500000])
            ->assertForbidden();
    }

    public function test_committee_officer_cannot_disburse(): void
    {
        $loan = $this->approvedFlatLoan($this->admin());

        $this->actingAs($this->userWithRole('committee_officer'), 'sanctum')
            ->postJson('/api/v1/admin/loans/'.$loan->id.'/disburse', ['amount_minor' => 500000])
            ->assertForbidden();
    }

    public function test_finance_officer_cannot_disburse(): void
    {
        $loan = $this->approvedFlatLoan($this->admin());

        $this->actingAs($this->userWithRole('finance_officer'), 'sanctum')
            ->postJson('/api/v1/admin/loans/'.$loan->id.'/disburse', ['amount_minor' => 500000])
            ->assertForbidden();
    }

    public function test_duplicate_disbursement_is_prevented_at_database_level(): void
    {
        $admin = $this->admin();
        $result = $this->disbursedLoan($admin);
        $loan = $result['loan'];
        $account = $loan->member->financialAccounts()->where('account_type', 'loan')->firstOrFail();

        // Attempt a direct duplicate insert inside a savepoint on the main
        // connection: the unique loan_id constraint must reject it, and rolling
        // back to the savepoint keeps the surrounding test transaction usable.
        $pdo = DB::connection()->getPdo();
        $pdo->exec('SAVEPOINT duplicate_disbursement_try');

        try {
            $pdo->exec(sprintf(
                "INSERT INTO loan_disbursements (id, loan_id, amount_minor, financial_account_id, status, disbursed_at, authorized_by, created_at, updated_at) VALUES ('%s', '%s', 500000, '%s', 'posted', NOW(), '%s', NOW(), NOW())",
                (string) Str::uuid(),
                $loan->id,
                $account->id,
                $admin->id,
            ));
            $this->fail('The unique loan_id constraint must reject a second disbursement row.');
        } catch (\PDOException $exception) {
            $pdo->exec('ROLLBACK TO SAVEPOINT duplicate_disbursement_try');
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount('loan_disbursements', 1);
    }

    // ------------------------------------------------- financial integrity

    public function test_no_mutable_balance_source_of_truth_is_introduced(): void
    {
        $this->assertFalse(Schema::hasColumn('loans', 'balance_minor'));
        $this->assertFalse(Schema::hasColumn('financial_accounts', 'balance_minor'));

        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $this->submitRepayment($loan, $loan->member->user, 30000);

        // Outstanding is derived from installment obligations minus posted
        // allocations - the Financial Core ledger stays authoritative.
        $summary = $loan->fresh()->obligationSummary();
        $this->assertSame(550000, $summary['total_obligation_minor']);
        $this->assertSame(0, $summary['total_paid_minor']);
        $this->assertSame(550000, $summary['total_outstanding_minor']);
    }

    public function test_monetary_values_use_integer_minor_units(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $this->assertIsInt($result['disbursement']->amount_minor);
        $this->assertIsInt($loan->fresh()->installments()->first()->principal_due_minor);
        $this->assertIsInt($loan->fresh()->installments()->first()->interest_due_minor);
        $this->assertIsInt($loan->fresh()->installments()->first()->total_due_minor);
        $this->assertIsInt($loan->fresh()->principal_amount_minor);
        $this->assertIsInt($loan->fresh()->interest_amount_minor);
    }

    public function test_posted_disbursement_transaction_is_immutable_and_undeletable(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $transaction = $result['transaction'];

        try {
            $transaction->update(['amount_minor' => 1]);
            $this->fail('Posted transactions must be immutable.');
        } catch (LogicException $exception) {
            $this->assertTrue(true);
        }

        try {
            $transaction->delete();
            $this->fail('Posted transactions must not be deletable.');
        } catch (LogicException $exception) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('financial_transactions', ['id' => $transaction->id, 'amount_minor' => 500000]);
    }

    public function test_posted_allocation_amounts_are_immutable(): void
    {
        $result = $this->disbursedLoan($this->admin());
        $loan = $result['loan'];

        $payment = $this->submitRepayment($loan, $loan->member->user, 10000);
        $this->verifyRepayment($payment);

        $allocation = LoanRepaymentAllocation::firstOrFail();

        try {
            $allocation->update(['principal_allocated_minor' => 99999]);
            $this->fail('Posted allocation amounts must be immutable.');
        } catch (LogicException $exception) {
            $this->assertTrue(true);
        }

        $this->assertSame(1666, (int) $allocation->fresh()->principal_allocated_minor);
    }
}
