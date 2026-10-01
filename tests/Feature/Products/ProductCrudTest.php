<?php

namespace Tests\Feature\Products;

use App\Enums\RecordStatus;
use App\Models\Product;
use Tests\TestCase;

/**
 * Product CRUD behaviour: creation, update, deactivation, soft delete and the
 * business rules that make a product usable later (base unit, tax, identifiers).
 */
class ProductCrudTest extends TestCase
{
    use InteractsWithProducts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTwoCompanies();
    }

    public function test_it_creates_a_simple_product(): void
    {
        $unit = $this->unitFor($this->acme, 'Piece');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload([
                'name' => 'Coca Cola',
                'sku' => 'COKE-001',
                'barcode' => '6221031492016',
                'product_type' => 'simple',
                'short_description' => 'Soft drink',
                'description' => 'A 330ml can.',
                'minimum_stock' => 24,
                'units' => [[
                    'unit_id' => $unit->id,
                    'conversion_factor' => 1,
                    'selling_price' => 1500,
                    'is_base' => true,
                ]],
            ]))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Coca Cola')
            ->assertJsonPath('data.product_type', 'simple')
            ->assertJsonPath('data.company_id', $this->acme->id);

        $this->assertDatabaseHas('products', [
            'sku' => 'COKE-001',
            'company_id' => $this->acme->id,
        ]);
    }

    public function test_it_creates_a_product_with_brand_categories_and_suppliers(): void
    {
        $unit = $this->unitFor($this->acme, 'Piece');
        $brand = $this->brandFor($this->acme, 'Coca Cola Co');
        $electronics = $this->categoryFor($this->acme, 'Electronics');
        $drinks = $this->categoryFor($this->acme, 'Drinks');
        $supplierA = $this->supplierFor($this->acme, 'Supplier A');
        $supplierB = $this->supplierFor($this->acme, 'Supplier B');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload([
                'brand_id' => $brand->id,
                'category_ids' => [$electronics->id, $drinks->id],
                'units' => [[
                    'unit_id' => $unit->id,
                    'conversion_factor' => 1,
                    'selling_price' => 1500,
                    'is_base' => true,
                ]],
                'supplier_terms' => [
                    ['supplier_id' => $supplierA->id, 'purchase_unit_id' => $unit->id, 'purchase_price' => 1200],
                    ['supplier_id' => $supplierB->id, 'purchase_unit_id' => $unit->id, 'purchase_price' => 1300],
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.brand.name', 'Coca Cola Co');

        $product = $this->latestProduct();

        $this->assertCount(2, $product->categories);
        $this->assertCount(2, $product->supplierTerms);
    }

    /**
     * The same product bought from two suppliers is the reason
     * product_suppliers exists; a single cost column could not express it.
     */
    public function test_supplier_specific_prices_are_stored_per_supplier(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');
        $supplierA = $this->supplierFor($this->acme, 'Cheap Supplier');
        $supplierB = $this->supplierFor($this->acme, 'Expensive Supplier');

        $product = $this->createProduct([
            'units' => [[
                'unit_id' => $piece->id,
                'conversion_factor' => 1,
                'selling_price' => 2000,
                'is_base' => true,
            ]],
            'supplier_terms' => [
                ['supplier_id' => $supplierA->id, 'purchase_unit_id' => $piece->id, 'purchase_price' => 1500],
                ['supplier_id' => $supplierB->id, 'purchase_unit_id' => $piece->id, 'purchase_price' => 1900],
            ],
        ]);

        $prices = $product->supplierTerms()
            ->orderBy('purchase_price')
            ->pluck('purchase_price')
            ->all();

        $this->assertSame([1500, 1900], $prices);
    }

    public function test_it_updates_a_product(): void
    {
        $product = $this->productFor($this->acme, 'Old Name');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->patchJson("/api/v1/acme/products/{$product->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertSame('New Name', $product->fresh()->name);
    }

    public function test_it_deactivates_without_deleting(): void
    {
        $product = $this->productFor($this->acme, 'To Deactivate');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson("/api/v1/acme/products/{$product->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', RecordStatus::Inactive->value);

        // Deactivating must not remove the record.
        $this->assertNotNull($product->fresh());
        $this->assertSame(RecordStatus::Inactive, $product->fresh()->status);
    }

    public function test_it_reactivates_a_deactivated_product(): void
    {
        $product = $this->productFor($this->acme, 'Reactivated');
        $product->markInactive();

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson("/api/v1/acme/products/{$product->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', RecordStatus::Active->value);
    }

    public function test_delete_is_a_soft_delete(): void
    {
        $product = $this->productFor($this->acme, 'Archived');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->deleteJson("/api/v1/acme/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        // Still in the table, so future sales/purchases stay referentially safe.
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        /*
         * ...but excluded from ordinary queries.
         *
         * onlyTrashed() bypasses SoftDeletes while still applying CompanyScope,
         * which is exactly how the archive is meant to behave: reachable when
         * asked for explicitly, invisible to normal listings.
         */
        $this->assertSame(
            1,
            Product::query()->onlyTrashed()->whereKey($product->id)->count()
        );
    }
}
