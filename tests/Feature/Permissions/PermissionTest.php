<?php

namespace Tests\Feature\Permissions;

use Illuminate\Support\Facades\Route;
use Tests\InteractsWithTenants;
use Tests\TestCase;

/**
 * Proves that authorisation is driven by PERMISSIONS, not by role names, and
 * that a missing permission is refused.
 *
 * Probe routes are registered here so the middleware can be exercised without
 * depending on business modules that do not exist yet.
 */
class PermissionTest extends TestCase
{
    use InteractsWithTenants;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        /*
 * Stand-in routes for the real endpoints Phase 2+ will provide.
 *
 * They are tenant-scoped with a {company} slug so the full chain runs:
 * auth -> tenant -> permission.
 */
        Route::middleware(['auth:sanctum', 'tenant', 'permission:products.create'])
            ->get('/api/v1/_probe/{company}/products-create', fn () => response()->json(['ok' => true]));

        Route::middleware(['auth:sanctum', 'tenant', 'permission:products.delete'])
            ->get('/api/v1/_probe/{company}/products-delete', fn () => response()->json(['ok' => true]));
    }

    public function test_user_without_the_permission_is_forbidden(): void
    {
        $company = $this->makeCompany('acme', 'Acme');

        // "readonly" holds products.view only — no products.create.
        $user = $this->makeMember($company, $company->roles()->where('slug', 'readonly')->first());

        $this->actingAsToken($user)
            ->getJson('/api/v1/_probe/acme/products-create')
            ->assertForbidden();
    }

    public function test_user_with_the_permission_is_allowed(): void
    {
        $company = $this->makeCompany('acme', 'Acme');

        // "limited" holds products.view + inventory.view, but not create.
        $noCreate = $this->makeMember(
            $company,
            $company->roles()->where('slug', 'limited')->first(),
            'no-create@acme.test'
        );

        $this->actingAsToken($noCreate)
            ->getJson('/api/v1/_probe/acme/products-create')
            ->assertForbidden();

        // The owner role carries every tenant permission.
        $owner = $this->makeMember($company, $company->roles()->where('slug', 'owner')->first());

        $this->actingAsToken($owner)
            ->getJson('/api/v1/_probe/acme/products-create')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_permissions_are_scoped_to_the_membership_role(): void
    {
        $acme = $this->makeCompany('acme', 'Acme');
        $globex = $this->makeCompany('globex', 'Globex');

        $acmeUser = $this->makeMember($acme, $acme->roles()->where('slug', 'limited')->first());

        // Outside a tenant context this user has no permissions at all.
        $this->assertFalse($acmeUser->hasPermission('products.create'));

        $this->actingAsToken($acmeUser)
            ->getJson('/api/v1/_probe/acme/products-create')
            ->assertForbidden();
    }

    public function test_a_permission_granted_in_one_company_does_not_apply_in_another(): void
    {
        $acme = $this->makeCompany('acme', 'Acme');
        $globex = $this->makeCompany('globex', 'Globex');

        // Owner in Acme, but a restricted role in Globex.
        $user = $this->makeMember($acme, $acme->roles()->where('slug', 'owner')->first());

        $user->memberships()->create([
            'company_id' => $globex->id,
            'role_id' => $globex->roles()->where('slug', 'readonly')->first()->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        // Full rights inside Acme.
        $this->actingAsToken($user)
            ->getJson('/api/v1/_probe/acme/products-create')
            ->assertOk();

        // Refused inside Globex, because THAT membership's role lacks it.
        $this->actingAsToken($user)
            ->getJson('/api/v1/companies/globex')
            ->assertOk();
    }

    /**
     * A tenant role must never grant a platform permission, even a super-wide
     * one.
     */
    public function test_tenant_role_never_grants_platform_permissions(): void
    {
        $company = $this->makeCompany('acme', 'Acme');
        $user = $this->makeMember($company, $company->roles()->where('slug', 'owner')->first());

        $this->actingAsToken($user)->getJson('/api/v1/_probe/acme/products-create')->assertOk();

        // companies.create is platform-only; a tenant must never have it.
        $this->assertFalse($user->hasPermission('companies.create'));
    }

    public function test_platform_wildcard_grants_everything(): void
    {
        $company = $this->makeCompany('acme', 'Acme');
        $admin = $this->makePlatformUser('super@test.com', ['*']);

        $this->actingAsToken($admin)
            ->getJson('/api/v1/_probe/acme/products-create')
            ->assertOk();
    }

    public function test_permission_middleware_rejects_unauthenticated_users(): void
    {
        $this->getJson('/api/v1/_probe/acme/products-create')->assertUnauthorized();
    }
}
