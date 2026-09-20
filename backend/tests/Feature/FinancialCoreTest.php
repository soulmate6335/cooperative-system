<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\FinancialCoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class FinancialCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_financial_account_belongs_to_an_active_member(): void
    {
        $member = $this->member();
        $account = app(FinancialCoreService::class)->createAccount($member, 'savings');
        $this->assertTrue(Str::isUuid($account->id));
        $this->assertSame($member->id, $account->member->id);
        $this->assertDatabaseHas('financial_accounts', ['id' => $account->id, 'account_type' => 'savings']);
    }

    public function test_inactive_member_cannot_receive_an_account(): void
    {
        $this->expectException(ValidationException::class);
        app(FinancialCoreService::class)->createAccount($this->member('suspended'), 'savings');
    }

    public function test_amount_below_one_hundred_naira_is_rejected(): void
    {
        $financeOfficer = $this->userWithRole('finance_officer');
        $member = $this->member();
        $response = $this->actingAs($financeOfficer, 'sanctum')->postJson('/api/v1/finance/payments', [
            'member_id' => $member->id, 'payment_method_id' => $this->paymentMethod()->id,
            'amount_minor' => 9999, 'payment_date' => now()->toISOString(),
            'reference_number' => 'PAY-MINIMUM-1', 'purpose' => 'savings',
        ]);
        $response->assertUnprocessable();
    }

    public function test_payment_is_pending_until_verified_then_posts_to_ledger(): void
    {
        $recorder = $this->userWithRole('finance_officer');
        $verifier = $this->userWithRole('finance_officer');
        $member = $this->member();
        $method = $this->paymentMethod();
        $account = app(FinancialCoreService::class)->createAccount($member, 'savings');
        $service = app(FinancialCoreService::class);
        $payment = $service->recordPayment([
            'member_id' => $member->id, 'payment_method_id' => $method->id,
            'amount_minor' => 10000, 'payment_date' => now(),
            'reference_number' => 'PAY-VALID-1', 'purpose' => 'savings',
        ], $recorder);
        $this->assertSame('pending', $payment->status);
        $this->assertSame(0, $service->balance($account));
        $transaction = $service->verifyPayment($payment, $verifier);
        $this->assertSame('posted', $transaction->status);
        $this->assertSame(10000, $service->balance($account));
    }

    public function test_payment_verification_is_idempotent(): void
    {
        $recorder = $this->userWithRole('finance_officer');
        $verifier = $this->userWithRole('finance_officer');
        $member = $this->member();
        $method = $this->paymentMethod();
        $service = app(FinancialCoreService::class);
        $service->createAccount($member, 'savings');
        $payment = $service->recordPayment([
            'member_id' => $member->id, 'payment_method_id' => $method->id,
            'amount_minor' => 10000, 'payment_date' => now(),
            'reference_number' => 'PAY-VERIFY-IDEMPOTENT', 'purpose' => 'savings',
        ], $recorder);
        $firstTransaction = $service->verifyPayment($payment, $verifier);
        $this->assertSame($payment->id, $firstTransaction->payment_id);
        try {
            $service->verifyPayment($payment, $verifier);
            $this->fail('A verified payment should not be posted twice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseHas('financial_transactions', ['payment_id' => $payment->id]);
    }

    public function test_payment_reference_is_unique(): void
    {
        $recorder = $this->userWithRole('finance_officer');
        $member = $this->member();
        $method = $this->paymentMethod();
        $service = app(FinancialCoreService::class);
        $data = ['member_id' => $member->id, 'payment_method_id' => $method->id, 'amount_minor' => 10000, 'payment_date' => now(), 'reference_number' => 'PAY-DUPLICATE', 'purpose' => 'contribution'];
        $service->recordPayment($data, $recorder);
        $this->expectExceptionMessage('reference_number');
        $service->recordPayment($data, $recorder);
    }

    public function test_receipt_requires_verified_payment(): void
    {
        $service = app(FinancialCoreService::class);
        $member = $this->member();
        $service->createAccount($member, 'contribution');
        $payment = $service->recordPayment(['member_id' => $member->id, 'payment_method_id' => $this->paymentMethod()->id, 'amount_minor' => 10000, 'payment_date' => now(), 'reference_number' => 'PAY-RECEIPT', 'purpose' => 'contribution'], $this->userWithRole('finance_officer'));
        $this->expectException(ValidationException::class);
        $service->issueReceipt($payment, $this->userWithRole('finance_officer'));
    }

    public function test_second_receipt_for_verified_payment_is_rejected(): void
    {
        $recorder = $this->userWithRole('finance_officer');
        $verifier = $this->userWithRole('finance_officer');
        $member = $this->member();
        $method = $this->paymentMethod();
        $service = app(FinancialCoreService::class);
        $service->createAccount($member, 'contribution');
        $payment = $service->recordPayment(['member_id' => $member->id, 'payment_method_id' => $method->id, 'amount_minor' => 10000, 'payment_date' => now(), 'reference_number' => 'PAY-RECEIPT-UNIQUE', 'purpose' => 'contribution'], $recorder);
        $service->verifyPayment($payment, $verifier);
        $firstReceipt = $service->issueReceipt($payment, $verifier);
        $this->assertSame($payment->id, $firstReceipt->payment_id);
        try {
            $service->issueReceipt($payment, $verifier);
            $this->fail('A second receipt should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        $this->actingAs($verifier, 'sanctum')->postJson('/api/v1/finance/payments/'.$payment->id.'/receipt')->assertUnprocessable();
        $this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_unposted_transactions_do_not_affect_balance(): void
    {
        $creator = $this->userWithRole('finance_officer');
        $account = app(FinancialCoreService::class)->createAccount($this->member(), 'shares');
        $transaction = FinancialTransaction::create(['account_id' => $account->id, 'transaction_type' => 'shares', 'direction' => 'credit', 'amount_minor' => 25000, 'transaction_date' => now(), 'reference' => 'PENDING-LEDGER', 'status' => 'pending', 'created_by' => $creator->id]);
        $service = app(FinancialCoreService::class);
        $this->assertSame(0, $service->balance($account));
        $transaction->update(['status' => 'posted', 'posted_by' => $creator->id, 'posted_at' => now()]);
        $this->assertSame(25000, $service->balance($account));
    }

    public function test_posted_transaction_cannot_be_edited(): void
    {
        $transaction = $this->postedTransaction('IMMUTABLE-LEDGER');
        $this->expectException(LogicException::class);
        $transaction->update(['description' => 'tampered']);
    }

    public function test_posted_transaction_cannot_be_deleted(): void
    {
        $transaction = $this->postedTransaction('IMMUTABLE-DELETE');
        $this->expectException(LogicException::class);
        $transaction->delete();
    }

    public function test_reversal_preserves_original_and_offsets_balance(): void
    {
        $creator = $this->userWithRole('finance_officer');
        $account = app(FinancialCoreService::class)->createAccount($this->member(), 'savings');
        $transaction = FinancialTransaction::create(['account_id' => $account->id, 'transaction_type' => 'savings', 'direction' => 'credit', 'amount_minor' => 10000, 'transaction_date' => now(), 'reference' => 'REVERSAL-SOURCE', 'status' => 'posted', 'created_by' => $creator->id, 'posted_by' => $creator->id, 'posted_at' => now()]);
        $reversal = app(FinancialCoreService::class)->reverse($transaction, $creator);
        $this->assertSame('debit', $reversal->direction);
        $this->assertSame(0, app(FinancialCoreService::class)->balance($account));
        $this->assertDatabaseHas('financial_transactions', ['id' => $transaction->id, 'status' => 'posted']);
    }

    public function test_member_cannot_record_administrative_financial_payment(): void
    {
        $memberUser = User::factory()->create();
        $member = $this->persistMember($memberUser);
        $this->actingAs($memberUser, 'sanctum')->postJson('/api/v1/finance/payments', ['member_id' => $member->id, 'payment_method_id' => $this->paymentMethod()->id, 'amount_minor' => 10000, 'payment_date' => now()->toISOString(), 'reference_number' => 'MEMBER-NOT-ALLOWED', 'purpose' => 'savings'])->assertForbidden();
    }

    public function test_users_without_financial_permissions_are_rejected(): void
    {
        $memberUser = User::factory()->create();
        $member = $this->persistMember($memberUser);
        $method = $this->paymentMethod();
        $this->actingAs($memberUser, 'sanctum')->postJson('/api/v1/admin/financial/accounts', ['member_id' => $member->id, 'account_type' => 'savings'])->assertForbidden();
        $this->actingAs($memberUser, 'sanctum')->postJson('/api/v1/admin/payment-methods', ['code' => 'bank-transfer', 'name' => 'Bank Transfer'])->assertForbidden();
        $this->actingAs($memberUser, 'sanctum')->postJson('/api/v1/finance/payments/'.$this->paymentFor($member, $method)->id.'/verify')->assertForbidden();
        $this->actingAs($memberUser, 'sanctum')->postJson('/api/v1/finance/payments/'.$this->paymentFor($member, $method)->id.'/receipt')->assertForbidden();
    }

    public function test_financial_permissions_are_checked_before_operations(): void
    {
        $financeOfficer = $this->userWithRole('finance_officer');
        $member = $this->member();
        $payment = $this->paymentFor($member, $this->paymentMethod());
        $transaction = app(FinancialCoreService::class)->verifyPayment($payment, $this->userWithRole('finance_officer'));
        $this->actingAs($financeOfficer, 'sanctum')->postJson('/api/v1/finance/transactions/'.$transaction->id.'/reverse')->assertForbidden();
        $otherMember = $this->member();
        $otherAccount = app(FinancialCoreService::class)->createAccount($otherMember, 'savings');
        $this->actingAs($financeOfficer, 'sanctum')->getJson('/api/v1/member/accounts/'.$otherAccount->id.'/transactions')->assertOk();
        $unprivileged = User::factory()->create();
        $this->actingAs($unprivileged, 'sanctum')->getJson('/api/v1/member/accounts/'.$otherAccount->id.'/transactions')->assertForbidden();
    }

    private function member(string $status = 'active'): Member
    {
        return $this->persistMember(User::factory()->create(['status' => 'active']), $status);
    }

    private function persistMember(User $user, string $status = 'active'): Member
    {
        return Member::create(['user_id' => $user->id, 'member_number' => 'MEM-'.Str::upper(Str::random(8)), 'joined_at' => now(), 'status' => $status, 'membership_type' => 'regular']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());

        return $user;
    }

    private function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::create(['code' => 'cash-'.Str::lower(Str::random(6)), 'name' => 'Cash', 'is_active' => true]);
    }

    private function paymentFor(Member $member, PaymentMethod $method): Payment
    {
        $recorder = $this->userWithRole('finance_officer');
        if (! $member->financialAccounts()->where('account_type', 'savings')->exists()) {
            app(FinancialCoreService::class)->createAccount($member, 'savings');
        }

        return app(FinancialCoreService::class)->recordPayment(['member_id' => $member->id, 'payment_method_id' => $method->id, 'amount_minor' => 10000, 'payment_date' => now(), 'reference_number' => 'PAY-'.strtoupper(Str::random(12)), 'purpose' => 'savings'], $recorder);
    }

    private function postedTransaction(string $reference): FinancialTransaction
    {
        $creator = $this->userWithRole('finance_officer');
        $account = app(FinancialCoreService::class)->createAccount($this->member(), 'savings');

        return FinancialTransaction::create(['account_id' => $account->id, 'transaction_type' => 'savings', 'direction' => 'credit', 'amount_minor' => 10000, 'transaction_date' => now(), 'reference' => $reference, 'status' => 'posted', 'created_by' => $creator->id, 'posted_by' => $creator->id, 'posted_at' => now()]);
    }
}
