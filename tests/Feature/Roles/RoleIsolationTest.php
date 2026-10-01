<?php

namespace Tests\Feature\Roles;

use App\Enums\RoleScope;
use App\Models\Role;
use Illuminate\Support\Facades\Route;
use Tests\InteractsWithTenants;
use Tests\TestCase;

/**
 * Proves that roles are company-scoped and that the two scopes (platform vs
 * tenant) cannot be mixed.
 */
class RoleIsolationTest extends TestCase
{
    use InteractsWithTenants;

    public function test_each_company_owns_its_own_role_rows(): void
    {
        $this->seedPermissions();
        $acme = $this->makeCompany('acme', 'Acme');
        $globex = $this->makeCompany('globex', 'Globex');

        $acmeRole = $acme->roles()->where('slug', 'owner')->first();
        $globexRole = $globex->roles()->where('slug', 'owner')->first();

        $this->assertNotSame($acmeRole->id, $globexRole->id);
        $this->assertSame($acme->id, $acmeRole->company_id);
        $this->assertSame($globex->id, $globexRole->company_id);
    }

    public function test_role_slug_may_repeat_across_companies(): void
    {
        $this->seedPermissions();
        $acme = $this->makeCompany('acme', 'Acme');
        $globex = $this->makeCompany('globex', 'Globex');

        // Both companies own an "owner" role: the unique index is per company.
        $this->assertSame(1, $acme->roles()->where('slug', 'owner')->count());
        $this->assertSame(1, $globex->roles()->where('slug', 'owner')->count());
    }

    public function test_platform_roles_have_no_company_and_tenant_roles_must_have_one(): void
    {
        $this->seedPermissions();

        $platform = Role::query()->create([
            'name' => 'Platform Admin',
            'slug' => 'platform-admin-'.uniqid(),
            'scope' => RoleScope::Platform->value,
            'company_id' => null,
        ]);

        $this->assertNull($platform->company_id);
        $this->assertTrue($platform->isPlatformRole());
        $this->assertFalse($platform->isTenantRole());

        $company = $this->makeCompany('acme', 'Acme');
        $tenantRole = $company->roles()->where('slug', 'owner')->first();

        $this->assertSame($company->id, $tenantRole->company_id);
        $this->assertTrue($tenantRole->isTenantRole());
    }

    /**
     * Editing a role in one company must never touch another company's role,
     * even when both share the same slug.
     */
    public function test_editing_a_role_in_one_company_does_not_affect_another(): void
    {
        $this->seedPermissions();
        $acme = $this->makeCompany('acme', 'Acme');
        $globex = $this->makeCompany('globex', 'Globex');

        $acmeRole = $acme->roles()->where('slug', 'limited')->first();
        $globexRole = $globex->roles()->where('slug', 'limited')->first();

        $originalGlobexPermissions = $globexRole->permissions()->pluck('name')->sort()->values()->all();

        // Modify only Acme's role.
        $acmeRole->update(['name' => 'Renamed In Acme']);
        $acmeRole->syncPermissions(['products.create']);

        $globexRole->refresh();

        $this->assertNotSame('Renamed In Acme', $globexRole->name);
        $this->assertSame(
            $originalGlobexPermissions,
            $globexRole->permissions()->pluck('name')->sort()->values()->all()
        );
    }

    public function test_deleting_a_company_role_cascade_does_not_touch_other_companies(): void
    {
        $this->seedPermissions();
        $acme = $this->makeCompany('acme', 'Acme');
        $globex = $this->makeCompany('globex', 'Globex');

        $globexRoleId = $globex->roles()->where('slug', 'limited')->first()->id;

        $acme->roles()->where('slug', 'limited')->first()->forceDelete();

        $this->assertDatabaseMissing('roles', ['id' => $acme->roles()->where('slug', 'limited')->first()?->id ?? 0]);
        $this->assertDatabaseHas('roles', ['id' => $globexRoleId]);
    }

    /**
     * Changing a role's permissions changes what its members can do, without any
     * code change — this is what "configurable roles" means in practice.
     *
     * The assertion goes through a real HTTP request because permissions are
     * resolved against the ACTIVE TENANT. Outside a tenant context a tenant
     * employee deliberately holds no permissions at all.
     */
    public function test_role_permissions_are_configurable_at_runtime(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('acme', 'Acme');

        Route::middleware(['auth:sanctum', 'tenant', 'permission:products.create'])
            ->get('/api/v1/_probe/{company}/create', fn () => response()->json(['ok' => true]));

        $role = $company->roles()->where('slug', 'readonly')->first();
        $user = $this->makeMember($company, $role);

        // Before granting: refused.
        $this->actingAsToken($user)
            ->getJson('/api/v1/_probe/acme/create')
            ->assertForbidden();

        // Grant the permission on the role at runtime.
        $role->syncPermissions(['products.view', 'products.create']);

        // After granting: allowed, with no code change.
        $this->actingAsToken($user)
            ->getJson('/api/v1/_probe/acme/create')
            ->assertOk();
    }
}
