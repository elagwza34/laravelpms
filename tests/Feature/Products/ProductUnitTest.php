<?php

namespace Tests\Feature\Products;

use Tests\TestCase;

/**
 * Units and unit conversion.
 *
 * Inventory is denominated in the product's BASE unit, so every other unit has
 * to be convertible through product_units. These tests lock that contract down
 * before the inventory phase depends on it.
 */
class ProductUnitTest extends TestCase
{
    use InteractsWithProducts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTwoCompanies();
    }

    public function test_it_creates_a_product_with_several_units(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');
        $box = $this->unitFor($this->acme, 'Box');
        $carton = $this->unitFor($this->acme, 'Carton');

        $product = $this->createProduct([
            'name' => 'Coca Cola',
            'units' => [
                ['unit_id' => $piece->id, 'conversion_factor' => 1, 'selling_price' => 1500, 'is_base' => true],
                ['unit_id' => $box->id, 'conversion_factor' => 12, 'selling_price' => 16500, 'is_base' => false],
                ['unit_id' => $carton->id, 'conversion_factor' => 24, 'selling_price' => 31000, 'is_base' => false],
            ],
        ]);

        $this->assertCount(3, $product->units);

        $base = $product->baseUnit();
        $this->assertNotNull($base);
        $this->assertSame($piece->id, $base->unit_id);
        $this->assertTrue($base->is_base);
    }

    /**
     * Selling 2 Boxes must translate to 24 base units — the number stock will
     * actually move by.
     */
    public function test_conversion_factor_translates_quantities_to_the_base_unit(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');
        $box = $this->unitFor($this->acme, 'Box');

        $product = $this->createProduct([
            'units' => [
                ['unit_id' => $piece->id, 'conversion_factor' => 1, 'selling_price' => 1500, 'is_base' => true],
                ['unit_id' => $box->id, 'conversion_factor' => 12, 'selling_price' => 16500, 'is_base' => false],
            ],
        ]);

        $boxRow = $product->units()->where('unit_id', $box->id)->firstOrFail();

        $this->assertEqualsWithDelta(24.0, $boxRow->toBaseQuantity(2), 0.001);
        $this->assertEqualsWithDelta(12.0, $boxRow->toBaseQuantity(1), 0.001);
    }

    /**
     * Prices are set per unit by the company, never derived from the base unit.
     */
    public function test_each_unit_carries_its_own_selling_price(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');
        $box = $this->unitFor($this->acme, 'Box');

        $product = $this->createProduct([
            'units' => [
                ['unit_id' => $piece->id, 'conversion_factor' => 1, 'selling_price' => 1500, 'is_base' => true],
                // Not 12 x 1500: the company controls this price.
                ['unit_id' => $box->id, 'conversion_factor' => 12, 'selling_price' => 20000, 'is_base' => false],
            ],
        ]);

        $this->assertSame(
            [1500, 20000],
            $product->units()->orderBy('selling_price')->pluck('selling_price')->all()
        );
    }

    public function test_exactly_one_base_unit_is_required(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');
        $box = $this->unitFor($this->acme, 'Box');
        $owner = $this->ownerOf($this->acme);

        // None marked as base.
        $this->actingAsToken($owner)
            ->postJson('/api/v1/acme/products', $this->productPayload(['units' => [
                ['unit_id' => $piece->id, 'conversion_factor' => 1, 'selling_price' => 1000, 'is_base' => false],
                ['unit_id' => $box->id, 'conversion_factor' => 12, 'selling_price' => 12000, 'is_base' => false],
            ]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('units');

        // Two marked as base.
        $this->actingAsToken($owner)
            ->postJson('/api/v1/acme/products', $this->productPayload(['units' => [
                ['unit_id' => $piece->id, 'conversion_factor' => 1, 'selling_price' => 1000, 'is_base' => true],
                ['unit_id' => $box->id, 'conversion_factor' => 1, 'selling_price' => 12000, 'is_base' => true],
            ]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('units');
    }

    public function test_the_base_unit_conversion_factor_must_be_one(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload(['units' => [
                ['unit_id' => $piece->id, 'conversion_factor' => 12, 'selling_price' => 1000, 'is_base' => true],
            ]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('units.0.conversion_factor');
    }

    public function test_conversion_factor_must_be_positive(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');

        foreach ([0, -5] as $factor) {
            $this->actingAsToken($this->ownerOf($this->acme))
                ->postJson('/api/v1/acme/products', $this->productPayload(['units' => [
                    ['unit_id' => $piece->id, 'conversion_factor' => $factor, 'selling_price' => 1000, 'is_base' => true],
                ]]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('units.0.conversion_factor');
        }
    }

    public function test_the_same_unit_cannot_be_listed_twice(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload(['units' => [
                ['unit_id' => $piece->id, 'conversion_factor' => 1, 'selling_price' => 1000, 'is_base' => true],
                ['unit_id' => $piece->id, 'conversion_factor' => 12, 'selling_price' => 12000, 'is_base' => false],
            ]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('units');
    }

    /**
     * A supplier may only sell in a unit the product actually offers.
     */
    public function test_a_supplier_purchase_unit_must_belong_to_the_product(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');
        $unusedUnit = $this->unitFor($this->acme, 'Pallet');
        $supplier = $this->supplierFor($this->acme, 'Supplier');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload([
                'units' => [$this->unitPayload($piece)],
                'supplier_terms' => [[
                    'supplier_id' => $supplier->id,
                    'purchase_unit_id' => $unusedUnit->id,
                    'purchase_price' => 1000,
                ]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('supplier_terms.0.purchase_unit_id');
    }

    public function test_the_same_supplier_cannot_be_attached_twice(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');
        $supplier = $this->supplierFor($this->acme, 'Supplier');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload([
                'units' => [$this->unitPayload($piece)],
                'supplier_terms' => [
                    ['supplier_id' => $supplier->id, 'purchase_unit_id' => $piece->id, 'purchase_price' => 1000],
                    ['supplier_id' => $supplier->id, 'purchase_unit_id' => $piece->id, 'purchase_price' => 1100],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('supplier_terms');
    }

    public function test_a_brand_is_optional(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');

        $this->createProduct([
            'name' => 'No Brand',
            'brand_id' => null,
            'units' => [$this->unitPayload($piece)],
        ]);

        $this->assertNull($this->latestProduct()->brand_id);
    }
}
