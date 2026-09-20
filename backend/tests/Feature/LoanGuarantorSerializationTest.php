<?php

namespace Tests\Feature;

use App\Models\LoanGuarantor;
use App\Models\Member;
use App\Services\LoanGuarantorService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLoanFixtures;

/**
 * The loan flow tests use RefreshDatabase, which keeps every application
 * connection inside one uncommitted transaction. A second connection can
 * never see (or lock) fixture rows there, so the cross-connection
 * serialization test lives in its own class where fixtures are committed
 * (migrate:fresh + autocommit, then db:wipe afterwards).
 */
class LoanGuarantorSerializationTest extends BaseTestCase
{
    use CreatesLoanFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->seed();
    }

    protected function tearDown(): void
    {
        $this->artisan('db:wipe');
        // Force the next RefreshDatabase test class to migrate fresh instead
        // of skipping it because of the shared static state.
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_guarantor_acceptance_is_serialized_by_a_member_row_lock(): void
    {
        $applicant = $this->loanMember();
        $guarantor = $this->loanMember();
        $product = $this->loanProduct(['required_guarantors' => 1]);
        $application = $this->submitApplication($this->draftApplication($applicant, $product));

        // Create the pending request directly instead of via the service so
        // the member row is not already locked by the application connection.
        $request = LoanGuarantor::create([
            'loan_application_id' => $application->id,
            'guarantor_member_id' => $guarantor->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        // Hold a FOR UPDATE lock on the guarantor member row from a second
        // connection. The service must block on that row before mutating
        // guarantee state, so the acceptance fails after the lock timeout.
        $pdo = $this->secondConnection();
        $pdo->beginTransaction();
        $statement = $pdo->prepare('SELECT id FROM members WHERE id = ? FOR UPDATE');
        $statement->execute([$guarantor->id]);

        DB::connection()->statement("SET lock_timeout = '1s'");

        try {
            app(LoanGuarantorService::class)->accept($request, $guarantor->user);
            $this->fail('Acceptance should have blocked on the guarantor member row lock.');
        } catch (QueryException) {
            // Lock timeout (SQLSTATE 55P03) proves serialization is in place.
        } finally {
            $pdo->rollBack();
        }

        $this->assertSame('pending', $request->fresh()->status);
    }
}
