<?php

namespace Database\Seeders;

use App\Enums\CompanyStatus;
use App\Enums\RoleScope;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Database\Seeder;

/**
 * DEMO / DEVELOPMENT ROLES.
 *
 * Creates platform roles plus the standard tenant role set for every demo
 * company. Safe to run repeatedly: records are matched on their natural keys.
 *
 * Role NAMES are labels only. What a role can actually do is defined purely by
 * the permissions attached below, so renaming a role never changes behaviour.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlatformRoles();
        $this->seedDemoCompanies();
    }

    /**
     * Platform roles belong to the SaaS operator and have no company_id.
     */
    private function seedPlatformRoles(): void
    {
        $superAdmin = Role::query()->updateOrCreate(
            ['slug' => 'super-admin', 'scope' => RoleScope::Platform->value, 'company_id' => null],
            [
                'name' => 'Super Admin',
                'description' => 'Unrestricted access to the whole platform.',
                'is_system' => true,
            ]
        );

        // The wildcard permission is what actually grants everything.
        $superAdmin->permissions()->sync(
            Permission::query()->where('name', PermissionCatalog::ALL)->pluck('id')
        );

        $admin = Role::query()->updateOrCreate(
            ['slug' => 'admin', 'scope' => RoleScope::Platform->value, 'company_id' => null],
            [
                'name' => 'Admin',
                'description' => 'Manages companies and platform users.',
                'is_system' => true,
            ]
        );

        $admin->permissions()->sync(
            Permission::query()->whereIn('name', [
                'companies.view', 'companies.create', 'companies.update',
                'platform_users.view', 'platform_users.manage',
                'roles.manage', 'subscriptions.view',
            ])->pluck('id')
        );

        $support = Role::query()->updateOrCreate(
            ['slug' => 'support', 'scope' => RoleScope::Platform->value, 'company_id' => null],
            [
                'name' => 'Support',
                'description' => 'Read-only platform visibility.',
                'is_system' => true,
            ]
        );

        $support->permissions()->sync(
            Permission::query()->whereIn('name', [
                'companies.view', 'platform_users.view', 'subscriptions.view',
            ])->pluck('id')
        );
    }

    /**
     * Two demo companies, each with the same role SLUGS but separate rows.
     *
     * Two companies with identically named roles are what make the isolation
     * tests meaningful: proving that "Owner of Demo" cannot touch "Owner of
     * Other" even though both are called Owner.
     */
    private function seedDemoCompanies(): void
    {
        foreach (['demo' => 'Demo Company', 'other' => 'Other Company'] as $slug => $name) {
            $company = Company::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'status' => CompanyStatus::Active->value]
            );

            $this->seedTenantRolesFor($company);
        }
    }

    /**
     * The standard role set every tenant gets and may customise.
     */
    private function seedTenantRolesFor(Company $company): void
    {
        $available = Permission::query()->pluck('id', 'name');

        foreach (TenantRoleDefinitions::for() as $slug => $definition) {
            $role = Role::query()->updateOrCreate(
                [
                    'slug' => $slug,
                    'scope' => RoleScope::Tenant->value,
                    'company_id' => $company->id,
                ],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_system' => true,
                ]
            );

            $role->permissions()->sync(
                collect($definition['permissions'])
                    ->map(fn (string $name): ?int => $available->get($name))
                    ->filter()
                    ->values()
                    ->all()
            );
        }
    }
}
