<?php

namespace Tests\Feature;

use App\Models\MemberApplication;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Notifications\GuarantorRequestAccepted;
use App\Notifications\GuarantorRequestDeclined;
use App\Notifications\GuarantorRequestReceived;
use App\Notifications\LoanApplicationSubmitted;
use App\Notifications\LoanApproved;
use App\Notifications\LoanDisbursed;
use App\Notifications\LoanRejected;
use App\Notifications\MembershipApplicationApproved;
use App\Notifications\MembershipApplicationRejected;
use App\Notifications\PaymentVerified;
use App\Services\LoanDecisionService;
use App\Services\LoanDisbursementService;
use App\Services\LoanGuarantorService;
use App\Services\LoanRepaymentService;
use App\Services\MembershipApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesLoanFixtures;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use CreatesLoanFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return $this->userWithRole('admin');
    }

    private function pendingApplicationFor(User $user): MemberApplication
    {
        return MemberApplication::create([
            'user_id' => $user->id,
            'application_number' => 'APP-'.strtoupper(Str::random(10)),
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => '08000000000',
            'address' => 'Example address',
            'date_of_birth' => '1990-01-01',
            'occupation' => 'Staff',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
    }

    // --------------------------------------------------- workflow events

    public function test_membership_approval_notifies_the_applicant(): void
    {
        $applicant = User::factory()->create(['status' => 'pending']);
        $application = $this->pendingApplicationFor($applicant);

        app(MembershipApplicationService::class)->approve($application, $this->admin());

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $applicant->id,
            'notifiable_type' => User::class,
            'type' => MembershipApplicationApproved::class,
        ]);
    }

    public function test_membership_rejection_notifies_the_applicant_with_the_reason(): void
    {
        $applicant = User::factory()->create(['status' => 'pending']);
        $application = $this->pendingApplicationFor($applicant);

        app(MembershipApplicationService::class)->reject($application, $this->admin(), 'Incomplete documentation.');

        $notification = $applicant->notifications()->where('type', MembershipApplicationRejected::class)->firstOrFail();
        $this->assertStringContainsString('Incomplete documentation', $notification->data['message']);
    }

    public function test_loan_submission_notifies_the_applicant_member(): void
    {
        $member = $this->loanMember();
        $application = $this->submitApplication($this->draftApplication($member, $this->loanProduct()));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $member->user_id,
            'notifiable_type' => User::class,
            'type' => LoanApplicationSubmitted::class,
        ]);

        $notification = $member->user->notifications()->firstWhere('type', LoanApplicationSubmitted::class);
        $this->assertStringContainsString($application->application_number, $notification->data['message']);
    }

    public function test_loan_approval_notifies_the_applicant_member(): void
    {
        $admin = $this->admin();
        $officer = $this->userWithRole('committee_officer');
        $product = $this->loanProduct(['interest_rate_basis_points' => 1000, 'interest_method' => 'flat', 'repayment_months' => 6, 'required_guarantors' => 2]);
        $member = $this->loanMember();
        $application = $this->pendingDecisionApplication($member, $product, $officer);

        $loan = $this->approveApplication($application, $admin, ['interest_method' => 'flat', 'repayment_months' => 6]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $member->user_id,
            'notifiable_type' => User::class,
            'type' => LoanApproved::class,
        ]);

        $notification = $member->user->notifications()->firstWhere('type', LoanApproved::class);
        $this->assertSame($loan->id, $notification->data['reference_id']);
        $this->assertSame($loan->loan_number, $notification->data['reference']);
    }

    public function test_loan_rejection_notifies_the_applicant_member(): void
    {
        $member = $this->loanMember();
        $application = $this->submitApplication($this->draftApplication($member, $this->loanProduct()));

        app(LoanDecisionService::class)->reject($application, $this->admin(), 'The member did not meet the eligibility criteria.');

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $member->user_id,
            'notifiable_type' => User::class,
            'type' => LoanRejected::class,
        ]);

        $notification = $member->user->notifications()->firstWhere('type', LoanRejected::class);
        $this->assertStringContainsString('did not meet the eligibility criteria', $notification->data['message']);
    }

    public function test_loan_disbursement_notifies_the_applicant_member(): void
    {
        $admin = $this->admin();
        $officer = $this->userWithRole('committee_officer');
        $product = $this->loanProduct(['interest_rate_basis_points' => 1000, 'interest_method' => 'flat', 'repayment_months' => 6, 'required_guarantors' => 2]);
        $member = $this->loanMember();
        $application = $this->pendingDecisionApplication($member, $product, $officer);
        $loan = $this->approveApplication($application, $admin, ['interest_method' => 'flat', 'repayment_months' => 6]);

        app(LoanDisbursementService::class)->disburse($loan, $admin, ['amount_minor' => (int) $loan->principal_amount_minor]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $member->user_id,
            'notifiable_type' => User::class,
            'type' => LoanDisbursed::class,
        ]);
    }

    public function test_repayment_verification_notifies_the_applicant_member(): void
    {
        $admin = $this->admin();
        $officer = $this->userWithRole('committee_officer');
        $product = $this->loanProduct(['interest_rate_basis_points' => 1000, 'interest_method' => 'flat', 'repayment_months' => 6, 'required_guarantors' => 2]);
        $member = $this->loanMember();
        $application = $this->pendingDecisionApplication($member, $product, $officer);
        $loan = $this->approveApplication($application, $admin, ['interest_method' => 'flat', 'repayment_months' => 6]);
        app(LoanDisbursementService::class)->disburse($loan, $admin, ['amount_minor' => (int) $loan->principal_amount_minor]);

        $payment = app(LoanRepaymentService::class)->submit([
            'member_id' => $loan->member_id,
            'loan_id' => $loan->id,
            'payment_method_id' => PaymentMethod::create(['code' => 'cash-'.Str::lower(Str::random(6)), 'name' => 'Cash', 'is_active' => true])->id,
            'amount_minor' => 100000,
            'payment_date' => now(),
            'reference_number' => 'REP-'.strtoupper(Str::random(10)),
        ], $admin);

        app(LoanRepaymentService::class)->verify($payment, $this->userWithRole('finance_officer'));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $member->user_id,
            'notifiable_type' => User::class,
            'type' => PaymentVerified::class,
        ]);

        $notification = $member->user->notifications()->firstWhere('type', PaymentVerified::class);
        $this->assertStringContainsString($loan->loan_number, $notification->data['message']);
    }

    public function test_guarantor_request_notifies_the_guarantor(): void
    {
        $applicant = $this->loanMember();
        $guarantor = $this->loanMember();
        $application = $this->submitApplication($this->draftApplication($applicant, $this->loanProduct()));

        app(LoanGuarantorService::class)->request($application, $applicant, $guarantor->id);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $guarantor->user_id,
            'notifiable_type' => User::class,
            'type' => GuarantorRequestReceived::class,
        ]);
    }

    public function test_guarantor_acceptance_notifies_the_applicant(): void
    {
        $applicant = $this->loanMember();
        $guarantor = $this->loanMember();
        $application = $this->submitApplication($this->draftApplication($applicant, $this->loanProduct()));
        $request = app(LoanGuarantorService::class)->request($application, $applicant, $guarantor->id);

        app(LoanGuarantorService::class)->accept($request, $guarantor->user);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $applicant->user_id,
            'notifiable_type' => User::class,
            'type' => GuarantorRequestAccepted::class,
        ]);
    }

    public function test_guarantor_decline_notifies_the_applicant(): void
    {
        $applicant = $this->loanMember();
        $guarantor = $this->loanMember();
        $application = $this->submitApplication($this->draftApplication($applicant, $this->loanProduct()));
        $request = app(LoanGuarantorService::class)->request($application, $applicant, $guarantor->id);

        app(LoanGuarantorService::class)->decline($request, $guarantor->user);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $applicant->user_id,
            'notifiable_type' => User::class,
            'type' => GuarantorRequestDeclined::class,
        ]);
    }

    // --------------------------------------------------------- inbox API

    public function test_notification_inbox_lists_only_the_current_users_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->notify(new LoanApproved('LN-USER', (string) Str::uuid()));
        $other->notify(new LoanDisbursed('LN-OTHER', (string) Str::uuid()));

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Loan approved', $response->json('data.0.title'));
        $this->assertSame('loan.approved', $response->json('data.0.type'));
    }

    public function test_unread_count_returns_the_number_of_unread_notifications(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->notify(new LoanApproved('LN-1', (string) Str::uuid()));
        $user->notify(new LoanApproved('LN-2', (string) Str::uuid()));
        $user->notifications()->first()->markAsRead();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);
    }

    public function test_a_notification_can_be_marked_as_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new LoanApproved('LN-1', (string) Str::uuid()));
        $notification = $user->notifications()->first();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_all_notifications_can_be_marked_as_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new LoanApproved('LN-1', (string) Str::uuid()));
        $user->notify(new LoanDisbursed('LN-2', (string) Str::uuid()));

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marked_read', 2);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_a_user_cannot_read_another_users_notification(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $other->notify(new LoanApproved('LN-1', (string) Str::uuid()));
        $foreignNotification = $other->notifications()->first();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/'.$foreignNotification->id.'/read')
            ->assertNotFound();

        $this->assertNull($foreignNotification->fresh()->read_at);
    }

    public function test_email_channel_is_attached_only_when_mail_is_enabled(): void
    {
        $user = User::factory()->create();
        $notification = new LoanApproved('LN-1', (string) Str::uuid());

        // The mail channel is attached only when the application is configured
        // for mail delivery; the database channel is always on.
        config(['mail.enabled' => true]);
        $this->assertSame(['database', 'mail'], $notification->via($user));

        $user->notify($notification);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);

        // Without mail configuration the in-app notification still lands.
        config(['mail.enabled' => false]);
        $this->assertSame(['database'], $notification->via($user));

        $second = User::factory()->create();
        $second->notify(new LoanApproved('LN-2', (string) Str::uuid()));
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $second->id]);
    }
}
