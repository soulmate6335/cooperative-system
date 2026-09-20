<?php

namespace Tests\Feature;

use App\Http\Resources\LoanProductResource;
use App\Models\LoanProduct;
use App\Services\LoanProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesLoanFixtures;
use Tests\TestCase;

class LoanProductCommitteeMeetingTest extends TestCase
{
    use CreatesLoanFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_create_a_loan_product(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/loan-products', [
            'name' => 'Staff Salary Advance',
            'description' => 'Short term salary advance.',
            'minimum_membership_months' => 6,
            'minimum_amount_minor' => 10000,
            'maximum_amount_minor' => 1000000,
            'interest_rate_basis_points' => 1000,
            'interest_method' => 'reducing_balance',
            'repayment_months' => 11,
            'required_guarantors' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Staff Salary Advance')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('loan_products', ['name' => 'Staff Salary Advance', 'status' => 'active', 'required_guarantors' => 2]);
    }

    public function test_member_cannot_manage_loan_products(): void
    {
        $memberUser = $this->loanMember()->user;

        $this->actingAs($memberUser, 'sanctum')->postJson('/api/v1/admin/loan-products', ['name' => 'X'])->assertForbidden();
        $this->actingAs($memberUser, 'sanctum')->postJson('/api/v1/admin/loan-products/'.LoanProduct::factory()->create()->id.'/deactivate')->assertForbidden();
    }

    public function test_invalid_interest_method_is_rejected(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/loan-products', [
            'name' => 'Bad Product',
            'interest_method' => 'compound',
        ])->assertUnprocessable();
    }

    public function test_maximum_amount_below_minimum_is_rejected(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/loan-products', [
            'name' => 'Bad Range',
            'minimum_amount_minor' => 200000,
            'maximum_amount_minor' => 100000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('maximum_amount_minor');
    }

    public function test_deactivate_then_activate_loan_product(): void
    {
        $admin = $this->userWithRole('admin');
        $product = $this->loanProduct();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/loan-products/'.$product->id.'/deactivate')->assertOk();
        $this->assertDatabaseHas('loan_products', ['id' => $product->id, 'status' => 'inactive']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/loan-products/'.$product->id.'/activate')->assertOk();
        $this->assertDatabaseHas('loan_products', ['id' => $product->id, 'status' => 'active']);
    }

    public function test_member_product_list_only_returns_active_products(): void
    {
        $this->loanProduct(['name' => 'Active A']);
        $this->loanProduct(['name' => 'Active B']);
        $this->loanProduct(['name' => 'Retired', 'status' => 'inactive']);
        $memberUser = $this->loanMember()->user;

        $response = $this->actingAs($memberUser, 'sanctum')->getJson('/api/v1/loan-products');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_can_create_committee_meeting_with_default_cutoff(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/committee-meetings', [
            'meeting_date' => now()->addDays(21)->toISOString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.cutoff_days', 14)
            ->assertJsonPath('data.status', 'scheduled');

        $this->assertDatabaseHas('committee_meetings', ['cutoff_days' => 14, 'status' => 'scheduled', 'created_by' => $admin->id]);
    }

    public function test_admin_can_hold_or_cancel_a_committee_meeting(): void
    {
        $admin = $this->userWithRole('admin');
        $meeting = $this->committeeMeeting();

        $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/admin/committee-meetings/'.$meeting->id, ['status' => 'cancelled'])->assertOk();
        $this->assertDatabaseHas('committee_meetings', ['id' => $meeting->id, 'status' => 'cancelled']);
    }

    public function test_member_cannot_manage_committee_meetings(): void
    {
        $memberUser = $this->loanMember()->user;

        $this->actingAs($memberUser, 'sanctum')->postJson('/api/v1/admin/committee-meetings', ['meeting_date' => now()->addDays(21)->toISOString()])->assertForbidden();
    }

    public function test_product_service_rejects_duplicate_activation(): void
    {
        $product = $this->loanProduct();

        try {
            app(LoanProductService::class)->setActive($product, true);
            $this->fail('Activating an already active product should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product', $exception->errors());
        }
    }

    public function test_product_resource_exposes_expected_shape(): void
    {
        $product = $this->loanProduct(['interest_rate_basis_points' => 1200]);

        $resource = LoanProductResource::make($product)->resolve();
        $this->assertSame($product->id, $resource['id']);
        $this->assertSame(1200, $resource['interest_rate_basis_points']);
        $this->assertSame(2, $resource['required_guarantors']);
    }
}
