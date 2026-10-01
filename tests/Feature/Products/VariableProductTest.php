<?php

namespace Tests\Feature\Products;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Variable products.
 *
 * The central rule: a VARIABLE PRODUCT IS STILL ONE INVENTORY ITEM. Variants
 * only record which attribute values were chosen — they carry no SKU, barcode,
 * price, cost, tax or stock. These tests assert that separation holds.
 */
class VariableProductTest extends TestCase
{
    use InteractsWithProducts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTwoCompanies();
    }

    public function test_it_creates_a_variable_product_with_generated_variants(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');
        ['values' => $colors] = $this->attributeFor($this->acme, 'Color', ['Black', 'White']);
        ['values' => $sizes] = $this->attributeFor($this->acme, 'Size', ['S', 'M']);

        // 2 colors x 2 sizes = 4 combinations.
        $product = $this->createProduct([
            'name' => 'T-Shirt',
            'product_type' => 'variable',
            'attribute_value_ids' => array_merge(
                $colors->pluck('id')->all(),
                $sizes->pluck('id')->all()
            ),
            'units' => [$this->unitPayload($piece)],
        ]);

        $this->assertTrue($product->isVariable());
        $this->assertCount(4, $product->variants);

        // Every variant carries exactly one color AND one size.
        $product->variants->each(function (ProductVariant $variant): void {
            $this->assertCount(2, $variant->attributeValues);
        });
    }

    /**
     * The rule that makes variants safe: no commercial data on the variant.
     */
    public function test_variants_carry_no_sku_barcode_or_price(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');
        ['values' => $colors] = $this->attributeFor($this->acme, 'Color', ['Black', 'White']);
        ['values' => $sizes] = $this->attributeFor($this->acme, 'Size', ['S', 'M']);

        $product = $this->createProduct([
            'product_type' => 'variable',
            'attribute_value_ids' => array_merge(
                $colors->pluck('id')->all(),
                $sizes->pluck('id')->all()
            ),
            'units' => [$this->unitPayload($piece)],
        ]);

        $columns = Schema::getColumnListing('product_variants');

        foreach (['sku', 'barcode', 'selling_price', 'purchase_price', 'cost', 'stock', 'tax_type', 'tax_value'] as $forbidden) {
            $this->assertNotContains(
                $forbidden,
                $columns,
                "product_variants must not carry {$forbidden}; that belongs to the product."
            );
        }

        // The commercial data lives on the product, exactly once.
        $this->assertSame(1, $product->units()->count());
    }

    public function test_variants_may_be_supplied_explicitly(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');
        ['values' => $colors] = $this->attributeFor($this->acme, 'Color', ['Black', 'White', 'Red']);
        ['values' => $sizes] = $this->attributeFor($this->acme, 'Size', ['S', 'M']);

        // Only two of the six possible combinations.
        $product = $this->createProduct([
            'product_type' => 'variable',
            'attribute_value_ids' => array_merge(
                $colors->pluck('id')->all(),
                $sizes->pluck('id')->all()
            ),
            'variants' => [
                [$colors->firstWhere('value', 'Black')->id, $sizes->firstWhere('value', 'S')->id],
                [$colors->firstWhere('value', 'White')->id, $sizes->firstWhere('value', 'M')->id],
            ],
            'units' => [$this->unitPayload($piece)],
        ]);

        $this->assertCount(2, $product->variants);
    }

    /**
     * The combination key is order-independent, so the same set always collides
     * instead of creating a duplicate.
     */
    public function test_duplicate_variant_combinations_are_rejected(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');
        ['values' => $colors] = $this->attributeFor($this->acme, 'Color', ['Black', 'White']);
        ['values' => $sizes] = $this->attributeFor($this->acme, 'Size', ['S', 'M']);

        $black = $colors->firstWhere('value', 'Black');
        $small = $sizes->firstWhere('value', 'S');
        $white = $colors->firstWhere('value', 'White');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload([
                'product_type' => 'variable',
                'attribute_value_ids' => array_merge(
                    $colors->pluck('id')->all(),
                    $sizes->pluck('id')->all()
                ),
                // The same two values listed in a different order.
                'variants' => [
                    [$black->id, $small->id],
                    [$small->id, $black->id],
                    [$white->id, $small->id],
                ],
                'units' => [$this->unitPayload($piece)],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('variants');
    }

    public function test_a_variant_needs_at_least_two_distinct_values(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');
        ['values' => $colors] = $this->attributeFor($this->acme, 'Color', ['Black', 'White']);

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload([
                'product_type' => 'variable',
                'attribute_value_ids' => $colors->pluck('id')->all(),
                'variants' => [[$colors->firstWhere('value', 'Black')->id]],
                'units' => [$this->unitPayload($piece)],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('variants.0');
    }

    public function test_a_variable_product_requires_attribute_values(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload([
                'product_type' => 'variable',
                'units' => [$this->unitPayload($piece)],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('attribute_value_ids');
    }

    public function test_a_simple_product_cannot_define_variants(): void
    {
        $piece = $this->unitFor($this->acme, 'Piece');
        ['values' => $colors] = $this->attributeFor($this->acme, 'Color', ['Black', 'White']);

        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/products', $this->productPayload([
                'product_type' => 'simple',
                'variants' => [$colors->pluck('id')->all()],
                'units' => [$this->unitPayload($piece)],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('variants');
    }

    public function test_combination_key_is_order_independent(): void
    {
        $this->assertSame(
            ProductVariant::combinationKeyFor([91, 12, 45]),
            ProductVariant::combinationKeyFor([45, 91, 12])
        );

        // Duplicates inside one list collapse.
        $this->assertSame(
            ProductVariant::combinationKeyFor([12, 45]),
            ProductVariant::combinationKeyFor([12, 45, 12])
        );

        // Different sets produce different keys.
        $this->assertNotSame(
            ProductVariant::combinationKeyFor([12, 45]),
            ProductVariant::combinationKeyFor([12, 46])
        );
    }

    /**
     * Attributes are company-level only: no global attributes exist, so two
     * companies may each define "Color" independently.
     */
    public function test_attributes_are_company_level_only(): void
    {
        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/attributes', ['name' => 'Color', 'values' => ['Black', 'White']])
            ->assertCreated()
            ->assertJsonPath('data.company_id', $this->acme->id);

        $this->actingAsToken($this->ownerOf($this->globex))
            ->postJson('/api/v1/globex/attributes', ['name' => 'Storage', 'values' => ['128GB']])
            ->assertCreated()
            ->assertJsonPath('data.company_id', $this->globex->id);

        // Acme cannot see Globex's attribute.
        $names = collect($this->actingAsToken($this->ownerOf($this->acme))
            ->getJson('/api/v1/acme/attributes')
            ->assertOk()
            ->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Color'));
        $this->assertFalse($names->contains('Storage'));
    }

    public function test_it_creates_attribute_values_under_an_attribute(): void
    {
        $created = $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/attributes', ['name' => 'Fabric', 'values' => ['Cotton', 'Linen']])
            ->assertCreated();

        $attributeId = $created->json('data.id');

        $this->assertCount(2, $created->json('data.values'));

        $value = $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson("/api/v1/acme/attributes/{$attributeId}/values", ['value' => 'Wool'])
            ->assertCreated()
            ->assertJsonPath('data.value', 'Wool');

        // The value inherits the attribute's company, never the client's.
        $this->assertSame($this->acme->id, $value->json('data.company_id'));
    }

    public function test_duplicate_attribute_values_are_rejected(): void
    {
        $this->actingAsToken($this->ownerOf($this->acme))
            ->postJson('/api/v1/acme/attributes', ['name' => 'Color', 'values' => ['Black', 'Black']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('values');
    }
}
