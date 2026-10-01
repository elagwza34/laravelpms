<?php

namespace Tests\Feature\Products;

use App\Models\Company;
use App\Models\Product;
use App\Models\Unit;
use Tests\InteractsWithTenants;
use Tests\TestCase;

/**
 * Authorisation for the product module.
 *
 * Proves that each verb requires its own permission, that a role name grants
 * nothing on its own, and that tenant permissions never satisfy a platform
 * permission.
 */
class ProductPermissionTest extends TestCase
{
    use InteractsWithTenants;

    private Company $acme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->acme = $this->makeCompany('acme', 'Acme');
    }

    public function test_a_user_with_products_view_can_list_but_not_create(): void
    {
        $viewer = $this->makeMember(
            $this->acme,
            $this->makeTenantRole($this->acme, 'viewer', ['products.view']),
            'viewer@acme.test'
        );

        $this->actingAsToken($viewer)
            ->getJson('/api/v1/acme/products')
            ->assertOk();

        $this->actingAsToken($viewer)
            ->postJson('/api/v1/acme/products', ['name' => 'X'])
            ->assertForbidden();
    }

    public function test_a_user_without_products_view_cannot_list(): void
    {
        // A role with no product permissions at all.
        $nobody = $this->makeMember(
            $this->acme,
            $this->makeTenantRole($this->acme, 'none', ['inventory.view']),
            'nobody@acme.test'
        );

        $this->actingAsToken($nobody)
            ->getJson('/api/v1/acme/products')
            ->assertForbidden();
    }

    public function test_each_verb_requires_its_own_permission(): void
    {
        $product = $this->makeProduct();

        // Create only.
        $creator = $this->makeMember(
            $this->acme,
            $this->makeTenantRole($this->acme, 'creator', ['products.view', 'products.create']),
            'creator@acme.test'
        );

        $this->actingAsToken($creator)
            ->patchJson("/api/v1/acme/products/{$product->id}", ['name' => 'Nope'])
            ->assertForbidden();

        $this->actingAsToken($creator)
            ->deleteJson("/api/v1/acme/products/{$product->id}")
            ->assertForbidden();

        // products.delete alone still must not allow update.
        $deleter = $this->makeMember(
            $this->acme,
            $this->makeTenantRole($this->acme, 'deleter', ['products.view', 'products.delete']),
            'deleter@acme.test'
        );

        $this->actingAsToken($deleter)
            ->patchJson("/api/v1/acme/products/{$product->id}", ['name' => 'Nope'])
            ->assertForbidden();

        $this->actingAsToken($deleter)
            ->deleteJson("/api/v1/acme/products/{$product->id}")
            ->assertOk();
    }

    public function test_an_owner_role_has_every_product_permission(): void
    {
        $owner = $this->makeMember(
            $this->acme,
            $this->acme->roles()->where('slug', 'owner')->firstOrFail(),
            'owner@acme.test'
        );

        $product = $this->makeProduct();

        $this->actingAsToken($owner)->getJson('/api/v1/acme/products')->assertOk();
        $this->actingAsToken($owner)->patchJson("/api/v1/acme/products/{$product->id}", ['name' => 'Renamed'])->assertOk();
    }

    /**
     * A tenant role must never satisfy a platform permission.
     *
     * The check runs inside an active tenant context, because permissions are
     * resolved against the tenant: outside it a tenant employee deliberately
     * holds none at all, which would make this assertion pass for the wrong
     * reason.
     */
    public function test_tenant_permissions_never_grant_platform_access(): void
    {
        $owner = $this->makeMember(
            $this->acme,
            $this->acme->roles()->where('slug', 'owner')->firstOrFail(),
            'owner@acme.test'
        );

        $this->withTenancyFor($this->acme);

        // The owner role carries every TENANT permission ...
        $this->assertTrue($owner->hasPermission('products.create'));

        // ... but no platform permission, whatever the tenant context.
        $this->assertFalse($owner->hasPermission('companies.create'));
        $this->assertFalse($owner->hasPermission('platform_users.manage'));

        $this->withoutTenancy();
    }

    public function test_master_data_requires_its_own_permissions(): void
    {
        $productOnly = $this->makeMember(
            $this->acme,
            $this->makeTenantRole($this->acme, 'product-only', ['products.view']),
            'productonly@acme.test'
        );

        // products.* does not grant brands.*, units.*, suppliers.* ...
        $this->actingAsToken($productOnly)->getJson('/api/v1/acme/brands')->assertForbidden();
        $this->actingAsToken($productOnly)->getJson('/api/v1/acme/units')->assertForbidden();
        $this->actingAsToken($productOnly)->getJson('/api/v1/acme/suppliers')->assertForbidden();
        $this->actingAsToken($productOnly)->getJson('/api/v1/acme/attributes')->assertForbidden();
        $this->actingAsToken($productOnly)->getJson('/api/v1/acme/categories')->assertForbidden();

        // ...but products.* still works.
        $this->actingAsToken($productOnly)->getJson('/api/v1/acme/products')->assertOk();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/acme/products')->assertUnauthorized();
    }

    private function makeProduct(): Product
    {
        $this->withTenancyFor($this->acme);

        $unit = Unit::factory()->create(['company_id' => $this->acme->id]);
        $product = Product::factory()->create(['company_id' => $this->acme->id]);

        $product->units()->create([
            'unit_id' => $unit->id,
            'conversion_factor' => 1,
            'selling_price' => 1000,
            'is_base' => true,
        ]);

        $this->withoutTenancy();

        return $product;
    }
}
