<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\Unit;
use Tests\TestCase;

/**
 * PROOF OF CROSS-TENANT ISOLATION FOR THE PRODUCT MODULE.
 *
 * Two companies are used throughout. Every test asserts Company A is refused
 * Company B's data — reading, updating, deleting and, most importantly,
 * ATTACHING foreign master data to a product.
 */
class ProductTenantIsolationTest extends TestCase
{
    use InteractsWithProducts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTwoCompanies();
    }

    // ---------------------------------------------------------------- LISTING

    public function test_listing_only_returns_the_active_companys_products(): void
    {
        $this->productFor($this->acme, 'Acme Product');
        $this->productFor($this->globex, 'Globex Product');

        $names = collect($this->actingAsToken($this->ownerOf($this->acme))
            ->getJson('/api/v1/acme/products')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Acme Product'));
        $this->assertFalse($names->contains('Globex Product'), 'Must not leak another company\'s products.');
    }

    public function test_search_cannot_reach_another_companys_product_by_name(): void
    {
        $this->productFor($this->globex, 'Confidential Widget');

        $response = $this->actingAsToken($this->ownerOf($this->acme))
            ->getJson('/api/v1/acme/products?search=Confidential')
            ->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    public function test_search_cannot_reach_another_companys_product_by_sku(): void
    {
        $this->productFor($this->globex, 'Globex Secret', sku: 'GLOBEX-SKU-1');

        $response = $this->actingAsToken($this->ownerOf($this->acme))
            ->getJson('/api/v1/acme/products?search=GLOBEX-SKU-1')
            ->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    // ------------------------------------------------------------ IDOR ON READ

    public function test_company_a_cannot_read_a_company_b_product_by_id(): void
    {
        $foreign = $this->productFor($this->globex, 'Globex Secret');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->getJson("/api/v1/acme/products/{$foreign->id}")
            ->assertNotFound();
    }

    // ---------------------------------------------------------- IDOR ON WRITE

    public function test_company_a_cannot_update_a_company_b_product(): void
    {
        $foreign = $this->productFor($this->globex, 'Globex Secret');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->patchJson("/api/v1/acme/products/{$foreign->id}", ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->assertSame('Globex Secret', $foreign->fresh()->name);
    }

    public function test_company_a_cannot_delete_a_company_b_product(): void
    {
        $foreign = $this->productFor($this->globex, 'Globex Secret');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->deleteJson("/api/v1/acme/products/{$foreign->id}")
            ->assertNotFound();

        $this->assertNotNull($foreign->fresh());
    }

    public function test_company_a_cannot_deactivate_a_company_b_product(): void
    {
        $foreign = $this->productFor($this->globex, 'Globex Secret');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson("/api/v1/acme/products/{$foreign->id}/deactivate")
            ->assertNotFound();

        $this->assertTrue($foreign->fresh()->isActive());
    }

    // ------------------------------------------- FOREIGN MASTER DATA ATTACHMENT

    public function test_company_a_cannot_attach_a_company_b_brand(): void
    {
        $foreignBrand = $this->brandFor($this->globex, 'Globex Brand');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload([
                'brand_id' => $foreignBrand->id,
                'units' => [$this->unitPayload($this->unitFor($this->acme))],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('brand_id');
    }

    public function test_company_a_cannot_attach_a_company_b_category(): void
    {
        $foreignCategory = $this->categoryFor($this->globex, 'Globex Electronics');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload([
                'category_ids' => [$foreignCategory->id],
                'units' => [$this->unitPayload($this->unitFor($this->acme))],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_ids.0');
    }

    public function test_company_a_cannot_attach_a_company_b_unit(): void
    {
        $foreignUnit = $this->unitFor($this->globex, 'Globex Box');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload([
                'units' => [$this->unitPayload($foreignUnit)],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('units.0.unit_id');
    }

    public function test_company_a_cannot_attach_a_company_b_supplier(): void
    {
        $ownUnit = $this->unitFor($this->acme);
        $foreignSupplier = $this->supplierFor($this->globex, 'Globex Supplier');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload([
                'units' => [$this->unitPayload($ownUnit)],
                'supplier_terms' => [[
                    'supplier_id' => $foreignSupplier->id,
                    'purchase_unit_id' => $ownUnit->id,
                    'purchase_price' => 500,
                ]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('supplier_terms.0.supplier_id');
    }

    public function test_company_a_cannot_use_a_company_b_attribute_value(): void
    {
        $foreign = $this->attributeFor($this->globex, 'Globex Color', ['Red']);

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload([
                'product_type' => 'variable',
                'attribute_value_ids' => [$foreign['values']->first()->id],
                'units' => [$this->unitPayload($this->unitFor($this->acme))],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('attribute_value_ids.0');
    }

    // ------------------------------------------------- TENANT SPOOFING ATTEMPTS

    /**
     * A client-supplied company_id is refused outright rather than silently
     * ignored, so the developer is told instead of left guessing.
     */
    public function test_company_id_in_the_body_is_rejected(): void
    {
        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload([
                'company_id' => $this->globex->id,
                'units' => [$this->unitPayload($this->unitFor($this->acme))],
            ]))
            ->assertStatus(422);
    }

    public function test_company_id_in_the_query_string_is_ignored(): void
    {
        $this->productFor($this->globex, 'Globex Secret');

        $response = $this->actingAsToken($this->ownerOf($this->acme))
            ->getJson('/api/v1/acme/products?company_id='.$this->globex->id)
            ->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    /**
     * Swapping the tenant slug in the URL while authenticated as Acme fails at
     * the middleware, because Acme holds no membership in Globex.
     */
    public function test_manipulating_the_tenant_slug_is_refused(): void
    {
        $this->actingAsToken($this->ownerOf($this->acme))
            ->getJson('/api/v1/globex/products')
            ->assertNotFound();
    }

    public function test_filtering_by_a_foreign_brand_returns_nothing(): void
    {
        $foreignBrand = $this->brandFor($this->globex, 'Globex Brand');
        $this->productFor($this->acme, 'Acme Product');

        $response = $this->actingAsToken($this->ownerOf($this->acme))
            ->getJson('/api/v1/acme/products?brand_id='.$foreignBrand->id)
            ->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function unitPayload(Unit $unit, bool $isBase = true): array
    {
        return [
            'unit_id' => $unit->id,
            'conversion_factor' => $isBase ? 1 : 12,
            'selling_price' => 1000,
            'is_base' => $isBase,
        ];
    }
}
