<?php

namespace Tests;

use App\Enums\CompanyStatus;
use App\Enums\MembershipStatus;
use App\Enums\RoleScope;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * Shared helpers for building multi-tenant fixtures.
 *
 * Two companies that both own a role with the SAME slug ("owner") is the key
 * to meaningful isolation tests: if the suite passed only because role names
 * differed globally, it would not prove that company scoping works.
 */
trait InteractsWithTenants
{
    use RefreshDatabase;

    /**
     * Seed the permission catalogue so roles can be attached to real rows.
     */
    protected function seedPermissions(): void
    {
        $this->seed(PermissionSeeder::class);
    }

    /**
     * Create a company with its standard role set.
     */
    protected function makeCompany(string $slug, string $name, CompanyStatus $status = CompanyStatus::Active): Company
    {
        $company = Company::factory()->create([
            'slug' => $slug,
            'name' => $name,
            'status' => $status,
        ]);

        $this->makeTenantRole($company, 'owner', permissions: array_keys(PermissionCatalog::tenant()));
        $this->makeTenantRole($company, 'limited', permissions: ['products.view', 'inventory.view']);
        $this->makeTenantRole($company, 'readonly', permissions: ['products.view']);

        return $company;
    }

    /**
     * Create a tenant-scoped role owned by a specific company.
     *
     * @param  array<int, string>  $permissions
     */
    protected function makeTenantRole(Company $company, string $slug, array $permissions = [], string $name = 'Test Role'): Role
    {
        $role = Role::query()->create([
            'name' => $name,
            'slug' => $slug,
            'scope' => RoleScope::Tenant->value,
            'company_id' => $company->id,
            'is_system' => false,
        ]);

        $role->permissions()->sync(
            Permission::query()->whereIn('name', $permissions)->pluck('id')
        );

        return $role;
    }

    /**
     * Create a user and attach them to a company with a role.
     */
    protected function makeMember(Company $company, Role $role, ?string $email = null): User
    {
        $user = User::factory()->create([
            'email' => $email ?? $role->slug.'@'.$company->slug.'.test',
            'password' => Hash::make('password'),
            'is_platform_user' => false,
        ]);

        CompanyMembership::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => MembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        return $user;
    }

    /**
     * Create a user with no membership anywhere.
     */
    protected function makePlainUser(string $email = 'nobody@test.com'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password'),
            'is_platform_user' => false,
        ]);
    }

    /**
     * Create a platform user and give it a platform role.
     *
     * @param  array<int, string>  $permissions
     */
    protected function makePlatformUser(string $email = 'platform@test.com', array $permissions = ['*']): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password'),
            'is_platform_user' => true,
        ]);

        $role = Role::query()->create([
            'name' => 'Platform Role',
            'slug' => 'platform-'.uniqid(),
            'scope' => RoleScope::Platform->value,
            'company_id' => null,
            'is_system' => false,
        ]);

        $role->permissions()->sync(Permission::query()->whereIn('name', $permissions)->pluck('id'));
        $user->platformRoles()->syncWithoutDetaching([$role->id]);

        return $user->fresh();
    }

    /**
     * A bearer token for the given user.
     */
    protected function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * Authenticate the next request as a specific user.
     *
     * Laravel resolves and caches the user inside the auth guard for the whole
     * test process, so simply calling withToken() again with a different user's
     * token would keep authenticating the FIRST user. forgetGuards() clears that
     * cache so each part of a multi-user test genuinely acts as its own user.
     */
    protected function actingAsToken(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->tokenFor($user));
    }
}
