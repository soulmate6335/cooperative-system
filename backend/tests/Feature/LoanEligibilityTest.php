<?php

namespace Tests\Feature;

use App\Contracts\LoanLimitRule;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Services\LoanEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesLoanFixtures;
use Tests\TestCase;

class LoanEligibilityTest extends TestCase
{
    use CreatesLoanFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_exactly_six_completed_calendar_months_is_eligible(): void
    {
        $this->travelTo('2026-06-01 12:00:00');
        $member = $this->loanMember('active', 6);
        $product = $this->loanProduct(['minimum_membership_months' => 6]);

        $factors = app(LoanEligibilityService::class)->factorsFor($member, $product);

        $this->assertSame(6, $factors['membership_months']);
        $this->assertTrue($factors['eligible']);
        $this->assertSame([], $factors['reasons']);
    }

    public function test_five_months_twenty_nine_days_is_not_eligible(): void
    {
        $this->travelTo('2026-06-01 12:00:00');
        $member = $this->loanMemberJoinedAt(now()->subMonths(5)->subDays(29));
        $product = $this->loanProduct(['minimum_membership_months' => 6]);

        $factors = app(LoanEligibilityService::class)->factorsFor($member, $product);

        $this->assertSame(5, $factors['membership_months']);
        $this->assertFalse($factors['eligible']);
        $this->assertNotEmpty($factors['reasons']);
    }

    public function test_configurable_minimum_membership_period_is_respected(): void
    {
        $this->travelTo('2026-06-01 12:00:00');

        $threeMonthMember = $this->loanMember('active', 3);
        $shortProduct = $this->loanProduct(['minimum_membership_months' => 3]);
        $this->assertTrue(app(LoanEligibilityService::class)->factorsFor($threeMonthMember, $shortProduct)['eligible']);

        $sixMonthMember = $this->loanMember('active', 6);
        $longProduct = $this->loanProduct(['minimum_membership_months' => 12]);
        $this->assertFalse(app(LoanEligibilityService::class)->factorsFor($sixMonthMember, $longProduct)['eligible']);
    }

    public function test_inactive_member_is_not_eligible(): void
    {
        $member = $this->loanMember('suspended', 6);
        $product = $this->loanProduct();

        $factors = app(LoanEligibilityService::class)->factorsFor($member, $product);

        $this->assertFalse($factors['member_active']);
        $this->assertFalse($factors['eligible']);
    }

    public function test_inactive_product_is_not_eligible(): void
    {
        $member = $this->loanMember('active', 6);
        $product = $this->loanProduct(['status' => 'inactive']);

        $factors = app(LoanEligibilityService::class)->factorsFor($member, $product);

        $this->assertFalse($factors['product_active']);
        $this->assertFalse($factors['eligible']);
    }

    public function test_savings_and_shares_balances_are_reported_as_informational_factors(): void
    {
        $member = $this->loanMember('active', 6);
        $this->seedBalance($member, 'savings', 150000);
        $this->seedBalance($member, 'shares', 50000);
        $product = $this->loanProduct();

        $factors = app(LoanEligibilityService::class)->factorsFor($member, $product);

        $this->assertSame(150000, $factors['savings_balance_minor']);
        $this->assertSame(50000, $factors['shares_balance_minor']);
    }

    public function test_computed_maximum_is_null_until_formula_is_defined(): void
    {
        $member = $this->loanMember('active', 6);
        $product = $this->loanProduct();

        $factors = app(LoanEligibilityService::class)->factorsFor($member, $product);

        $this->assertNull($factors['computed_maximum_amount_minor']);
    }

    public function test_loan_limit_rule_extension_point_exists_while_computed_maximum_stays_null(): void
    {
        $this->assertTrue(interface_exists(LoanLimitRule::class));

        $member = $this->loanMember('active', 6);
        $product = $this->loanProduct();

        $factors = app(LoanEligibilityService::class)->factorsFor($member, $product);

        // No rule is registered, so the computed maximum stays null (D-1).
        $this->assertNull($factors['computed_maximum_amount_minor']);
    }

    public function test_a_registered_loan_limit_rule_is_consulted_for_the_computed_maximum(): void
    {
        $this->app->instance(LoanLimitRule::class, new class implements LoanLimitRule
        {
            public function computedMaximumMinor(Member $member, LoanProduct $product): ?int
            {
                return 250000;
            }
        });

        $member = $this->loanMember('active', 6);
        $product = $this->loanProduct();

        $factors = app(LoanEligibilityService::class)->factorsFor($member, $product);

        $this->assertSame(250000, $factors['computed_maximum_amount_minor']);
    }

    public function test_eligibility_endpoint_returns_factors_for_member(): void
    {
        $member = $this->loanMember('active', 6);
        $product = $this->loanProduct();

        $response = $this->actingAs($member->user, 'sanctum')->getJson('/api/v1/member/loans/eligibility?product_id='.$product->id);

        $response->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.minimum_membership_months', 6);
    }
}
