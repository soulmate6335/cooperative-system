<?php

namespace Tests\Feature;

use App\Models\MemberApplication;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_authenticate_and_receive_a_sanctum_token(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);
        $response = $this->postJson('/api/v1/auth/login', ['email' => 'member@example.com', 'password' => 'password']);
        $response->assertOk()->assertJsonPath('data.user.email', $user->email);
        $response->assertJsonMissingPath('data.user.password');
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_type' => User::class, 'tokenable_id' => $user->id]);
    }

    public function test_unauthenticated_user_cannot_access_current_user_endpoint(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_registration_creates_a_pending_application_and_stores_photo_privately(): void
    {
        Storage::fake('local');
        $response = $this->post('/api/v1/membership/applications', [
            'full_name' => 'New Applicant', 'email' => 'applicant@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
            'phone' => '08000000000', 'address' => 'Example address',
            'date_of_birth' => '1990-01-01', 'occupation' => 'Staff',
            'department' => 'Engineering', 'profile_photo' => $this->profilePhoto(),
        ]);
        $response->assertCreated()->assertJsonPath('data.status', 'pending');
        $application = MemberApplication::where('email', 'applicant@example.com')->firstOrFail();
        $this->assertDatabaseHas('users', ['email' => 'applicant@example.com', 'status' => 'pending']);
        $this->assertDatabaseHas('member_applications', ['id' => $application->id, 'status' => 'pending']);
        Storage::disk('local')->assertExists($application->profile_photo);
    }

    public function test_duplicate_registration_is_rejected(): void
    {
        $payload = [
            'full_name' => 'Duplicate Applicant', 'email' => 'duplicate@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
            'phone' => '08000000000', 'address' => 'Example address',
            'date_of_birth' => '1990-01-01', 'occupation' => 'Staff',
            'profile_photo' => $this->profilePhoto(),
        ];
        $this->post('/api/v1/membership/applications', $payload)->assertCreated();
        $this->post('/api/v1/membership/applications', $payload)->assertUnprocessable();
    }

    public function test_logout_revokes_the_current_sanctum_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('logout-test')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->refreshApplication();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_member_cannot_approve_an_application(): void
    {
        $member = User::factory()->create();
        $application = $this->applicationFor(User::factory()->create());
        $this->actingAs($member, 'sanctum')->postJson("/api/v1/admin/membership/applications/{$application->id}/approve")->assertForbidden();
    }

    public function test_admin_can_approve_application_and_create_member(): void
    {
        $admin = $this->userWithRole('admin');
        $applicant = User::factory()->create(['status' => 'pending']);
        $application = $this->applicationFor($applicant);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/membership/applications/{$application->id}/approve")->assertOk();
        $this->assertDatabaseHas('member_applications', ['id' => $application->id, 'status' => 'approved', 'reviewed_by' => $admin->id]);
        $this->assertDatabaseHas('members', ['user_id' => $applicant->id, 'approved_by' => $admin->id, 'status' => 'active']);
        $this->assertDatabaseHas('users', ['id' => $applicant->id, 'status' => 'active']);
    }

    public function test_rejected_application_records_reason_and_suspends_user(): void
    {
        $admin = $this->userWithRole('admin');
        $applicant = User::factory()->create(['status' => 'pending']);
        $application = $this->applicationFor($applicant);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/membership/applications/{$application->id}/reject", ['reason' => 'The submitted information could not be verified.'])->assertOk();
        $this->assertDatabaseHas('member_applications', ['id' => $application->id, 'status' => 'rejected', 'rejection_reason' => 'The submitted information could not be verified.', 'reviewed_by' => $admin->id]);
        $this->assertDatabaseHas('users', ['id' => $applicant->id, 'status' => 'suspended']);
    }

    public function test_role_permissions_are_checked_independently(): void
    {
        $financeOfficer = $this->userWithRole('finance_officer');
        $application = $this->applicationFor(User::factory()->create(['status' => 'pending']));
        $this->actingAs($financeOfficer, 'sanctum')->postJson("/api/v1/admin/membership/applications/{$application->id}/approve")->assertForbidden();
    }

    public function test_committee_officer_cannot_approve_membership(): void
    {
        $committeeOfficer = $this->userWithRole('committee_officer');
        $application = $this->applicationFor(User::factory()->create(['status' => 'pending']));
        $this->actingAs($committeeOfficer, 'sanctum')->postJson("/api/v1/admin/membership/applications/{$application->id}/approve")->assertForbidden();
    }

    public function test_admin_cannot_approve_their_own_application(): void
    {
        $admin = $this->userWithRole('admin');
        $application = $this->applicationFor($admin);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/membership/applications/{$application->id}/approve")->assertForbidden();
    }

    public function test_role_permission_boundaries_match_documented_roles(): void
    {
        $financeOfficer = $this->userWithRole('finance_officer');
        $committeeOfficer = $this->userWithRole('committee_officer');
        $superAdmin = $this->userWithRole('super_admin');
        $this->assertTrue($financeOfficer->hasPermission('payments.verify'));
        $this->assertFalse($financeOfficer->hasPermission('members.approve'));
        $this->assertTrue($committeeOfficer->hasPermission('loans.investigate'));
        $this->assertFalse($committeeOfficer->hasPermission('loans.approve'));
        $this->assertTrue($superAdmin->hasPermission('roles.manage'));
    }

    public function test_uuid_relationships_and_sanctum_tokenable_id_are_preserved(): void
    {
        $user = User::factory()->create();
        $application = $this->applicationFor($user);
        $token = $user->createToken('test-token')->accessToken;
        $this->assertTrue(Str::isUuid($user->id));
        $this->assertTrue(Str::isUuid($application->id));
        $this->assertSame($user->id, $application->user->id);
        $this->assertSame($user->id, $token->tokenable_id);
        $this->assertSame($user->id, $token->tokenable->id);
    }

    private function applicationFor(User $user): MemberApplication
    {
        return MemberApplication::create([
            'user_id' => $user->id, 'application_number' => 'APP-'.strtoupper(Str::random(10)),
            'full_name' => $user->name, 'email' => $user->email, 'phone' => '08000000000',
            'address' => 'Example address', 'date_of_birth' => '1990-01-01',
            'occupation' => 'Staff', 'status' => 'pending', 'submitted_at' => now(),
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }

    private function profilePhoto(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'profile.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
            'image/png',
        );
    }
}
