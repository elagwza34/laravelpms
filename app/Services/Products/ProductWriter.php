<?php

namespace App\Services\Products;

use App\Enums\ProductType;
use App\Enums\RecordStatus;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\Tenancy\TenancyContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Writes a product and its dependent rows as one atomic operation.
 *
 * WHY A SERVICE
 * -------------
 * A product is not a single row. Creating one also writes its units, its
 * supplier terms, its category links and — for a variable product — its
 * variants. Spreading that across a controller risks a product that exists with
 * no base unit, which would break every later stock calculation. So the whole
 * graph is written inside one transaction here, and the controller stays thin.
 *
 * TENANT SAFETY
 * -------------
 * This class NEVER accepts a company id. Every related entity (brand,
 * categories, units, suppliers, attribute values) is looked up through its
 * tenant-scoped model by the FormRequest before we get here, so a foreign id is
 * simply not found and the write is refused. company_id is copied from the
 * product itself.
 */
class ProductWriter
{
    public function __construct(
        protected VariantCombinationBuilder $combinations,
        protected TenancyContext $tenancy,
    ) {}

    /**
     * Create a product with its units, categories, supplier terms and variants.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{unit_id:int, conversion_factor:float|int|string, selling_price:int, is_base?:bool}>  $units
     * @param  array<int, int>  $categoryIds
     * @param  array<int, array{supplier_id:int, purchase_unit_id:int, purchase_price:int}>  $supplierTerms
     * @param  array<int, array<int, int>>|null  $variants  sets of attribute_value ids; null = full matrix
     * @param  array<int, int>  $attributeValueIds  values offered by the product
     */
    public function create(array $attributes, array $units, array $categoryIds = [], array $supplierTerms = [], ?array $variants = null, array $attributeValueIds = []): Product
    {
        return DB::transaction(function () use ($attributes, $units, $categoryIds, $supplierTerms, $variants, $attributeValueIds): Product {
            $product = Product::query()->create(
                /*
                 * status is defaulted here rather than left to the column default:
                 * after insert() the model is not re-read, so the attribute would
                 * stay null and the API would describe a record that is not the one
                 * actually stored.
                 */
                $attributes + ['status' => RecordStatus::Active->value]
            );

            $this->syncUnits($product, $units);
            $this->attachCategories($product, $categoryIds);
            $this->syncSupplierTerms($product, $supplierTerms);
            $this->syncVariants($product, $variants, $attributeValueIds);

            return $product->load(Product::detailRelations());
        });
    }

    /**
     * Update a product and replace its dependent rows.
     *
     * A null argument leaves that collection untouched, which is what makes a
     * PATCH that only changes the name safe.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>|null  $units
     * @param  array<int, int>|null  $categoryIds
     * @param  array<int, array<string, mixed>>|null  $supplierTerms
     * @param  array<int, array<int, int>>|null  $variants
     * @param  array<int, int>|null  $attributeValueIds
     */
    public function update(Product $product, array $attributes, ?array $units = null, ?array $categoryIds = null, ?array $supplierTerms = null, ?array $variants = null, ?array $attributeValueIds = null): Product
    {
        return DB::transaction(function () use ($product, $attributes, $units, $categoryIds, $supplierTerms, $variants, $attributeValueIds): Product {
            $product->fill($attributes);
            $product->save();

            if ($units !== null) {
                $this->syncUnits($product, $units);
            }

            if ($categoryIds !== null) {
                $this->attachCategories($product, $categoryIds);
            }

            if ($supplierTerms !== null) {
                $product->supplierTerms()->delete();
                $this->syncSupplierTerms($product, $supplierTerms);
            }

            if ($variants !== null) {
                $product->variants()->delete();
                $this->syncVariants($product, $variants, $attributeValueIds ?? []);
            }

            return $product->load(Product::detailRelations());
        });
    }

    /**
     * Replace the product's units.
     *
     * Exactly one row must carry is_base, and that row's factor must be 1 —
     * the invariant every later stock conversion depends on. Validation rejects
     * bad input up front; this method refuses to persist it regardless.
     *
     * @param  array<int, array<string, mixed>>  $units
     */
    private function syncUnits(Product $product, array $units): void
    {
        $product->units()->delete();

        $product->units()->createMany(
            collect($units)->map(fn (array $unit): array => [
                'unit_id' => $unit['unit_id'],
                'conversion_factor' => $unit['conversion_factor'],
                'selling_price' => $unit['selling_price'],
                'is_base' => (bool) ($unit['is_base'] ?? false),
            ])->all()
        );

        $baseCount = ProductUnit::query()
            ->where('product_id', $product->id)
            ->where('is_base', true)
            ->count();

        if ($baseCount !== 1) {
            throw new RuntimeException('A product must have exactly one base unit.');
        }
    }

    /**
     * Attach categories.
     *
     * The ids were already resolved through the tenant-scoped Category model by
     * the FormRequest, so a category from another company is never linked.
     *
     * @param  array<int, int>  $categoryIds
     */
    private function attachCategories(Product $product, array $categoryIds): void
    {
        $product->categories()->sync($categoryIds);
    }

    /**
     * @param  array<int, array{supplier_id:int, purchase_unit_id:int, purchase_price:int}>  $supplierTerms
     */
    private function syncSupplierTerms(Product $product, array $supplierTerms): void
    {
        if ($supplierTerms === []) {
            return;
        }

        $product->supplierTerms()->createMany(
            collect($supplierTerms)->map(fn (array $term): array => [
                'supplier_id' => $term['supplier_id'],
                'purchase_unit_id' => $term['purchase_unit_id'],
                'purchase_price' => $term['purchase_price'],
            ])->all()
        );
    }

    /**
     * Replace the product's variants.
     *
     * A simple product has none by definition. For a variable product the caller
     * may pass explicit combinations, or omit them to generate the full matrix
     * from the offered attribute values.
     *
     * @param  array<int, array<int, int>>|null  $variants
     * @param  array<int, int>  $attributeValueIds
     */
    private function syncVariants(Product $product, ?array $variants, array $attributeValueIds): void
    {
        if ($product->product_type !== ProductType::Variable) {
            return;
        }

        if ($attributeValueIds === []) {
            throw new RuntimeException('A variable product requires at least one attribute value.');
        }

        /*
         * Resolving first proves every value belongs to this company BEFORE any
         * row is written, so a partial variant tree can never be persisted.
         */
        $values = $this->combinations->resolveValues($attributeValueIds);

        $combinations = $variants ?? $this->combinations->combinationsFor($values);

        if ($combinations === []) {
            throw new RuntimeException('A variable product requires at least one variant combination.');
        }

        $this->combinations->createVariants($product, $combinations);
    }
}
