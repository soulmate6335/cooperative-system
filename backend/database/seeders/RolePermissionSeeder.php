<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'members.view' => 'View member records.',
            'members.review' => 'Review membership applications.',
            'members.approve' => 'Approve membership applications.',
            'members.reject' => 'Reject membership applications.',
            'payments.create' => 'Record financial payments.',
            'payments.view' => 'View financial payments.',
            'payments.verify' => 'Verify financial payments.',
            'receipts.create' => 'Issue payment receipts.',
            'accounts.manage' => 'Create and manage financial accounts.',
            'payment_methods.manage' => 'Manage configurable payment methods.',
            'transactions.create' => 'Record financial transactions.',
            'transactions.view' => 'View financial transaction records.',
            'transactions.verify' => 'Verify financial transactions.',
            'transactions.reverse' => 'Authorize financial transaction reversals.',
            'loans.view' => 'View loan records and applications.',
            'loans.apply' => 'Apply for loans and manage own applications.',
            'loans.guarantee' => 'Respond to loan guarantee requests.',
            'loans.investigate' => 'Investigate loan applications.',
            'loans.approve' => 'Approve loan applications.',
            'loans.reject' => 'Reject loan applications.',
            'loans.disburse' => 'Authorize loan disbursement.',
            'loan_products.manage' => 'Create and manage loan products.',
            'committee_meetings.manage' => 'Create and manage committee meetings.',
            'notices.manage' => 'Manage notices and public content.',
            'executives.manage' => 'Manage executive profiles.',
            'settings.manage' => 'Manage organization settings.',
            'reports.view' => 'View authorized reports.',
            'audit.view' => 'View audit logs.',
            'users.manage' => 'Manage user accounts.',
            'roles.manage' => 'Manage roles and permissions.',
        ];

        foreach ($permissions as $name => $description) {
            Permission::updateOrCreate(['name' => $name], ['description' => $description]);
        }

        $roles = [
            'member' => ['members.view', 'loans.view', 'loans.apply', 'loans.guarantee'],
            'finance_officer' => ['members.view', 'loans.view', 'payments.view', 'payments.create', 'payments.verify', 'receipts.create', 'accounts.manage', 'transactions.create', 'transactions.view', 'transactions.verify', 'reports.view'],
            'committee_officer' => ['members.view', 'loans.view', 'loans.investigate'],
            'admin' => [
                'members.view', 'members.review', 'members.approve', 'members.reject', 'loans.view', 'loans.investigate',
                'loans.approve', 'loans.reject', 'loans.disburse', 'notices.manage', 'loan_products.manage',
                'committee_meetings.manage',
                'executives.manage', 'settings.manage', 'payment_methods.manage', 'payments.view', 'payments.verify',
                'receipts.create', 'accounts.manage', 'transactions.view', 'transactions.reverse', 'reports.view',
            ],
            'super_admin' => array_keys($permissions),
        ];

        foreach ($roles as $name => $permissionNames) {
            $role = Role::updateOrCreate(['name' => $name]);
            $role->permissions()->sync(Permission::whereIn('name', $permissionNames)->pluck('id'));
        }
    }
}
