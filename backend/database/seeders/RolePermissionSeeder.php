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
            'payments.verify' => 'Verify financial payments.',
            'transactions.create' => 'Record financial transactions.',
            'transactions.view' => 'View financial transaction records.',
            'transactions.verify' => 'Verify financial transactions.',
            'loans.investigate' => 'Investigate loan applications.',
            'loans.approve' => 'Approve loan applications.',
            'loans.reject' => 'Reject loan applications.',
            'loans.disburse' => 'Authorize loan disbursement.',
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
            'member' => ['members.view'],
            'finance_officer' => ['members.view', 'payments.create', 'payments.verify', 'transactions.create', 'transactions.view', 'transactions.verify', 'reports.view'],
            'committee_officer' => ['members.view', 'loans.investigate'],
            'admin' => [
                'members.view', 'members.review', 'members.approve', 'members.reject', 'loans.investigate',
                'loans.approve', 'loans.reject', 'loans.disburse', 'notices.manage',
                'executives.manage', 'settings.manage', 'reports.view',
            ],
            'super_admin' => array_keys($permissions),
        ];

        foreach ($roles as $name => $permissionNames) {
            $role = Role::updateOrCreate(['name' => $name]);
            $role->permissions()->sync(Permission::whereIn('name', $permissionNames)->pluck('id'));
        }
    }
}
