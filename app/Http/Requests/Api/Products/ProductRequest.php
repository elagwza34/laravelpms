<?php

namespace App\Http\Requests\Api\Products;

use App\Enums\ProductType;
use App\Enums\TaxType;
use App\Http\Requests\Api\ApiFormRequest;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation for creating and updating a product.
 *
 * Every foreign key is checked against the ACTIVE TENANT via tenantExists(),
 * never with a bare exists() rule, so referencing another company's brand,
 * category, unit, supplier or attribute value is refused rather than accepted.
 */
abstract class ProductRequest extends ApiFormRequest
{
    /**
     * The product being updated, or null when creating.
     */
    abstract protected function productBeingUpdated(): ?Product;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->productBeingUpdated() === null;

        return [
            'name' => [$required ? 'required' : 'sometimes', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'product_type' => [$required ? 'required' : 'sometimes', Rule::in(ProductType::values())],

            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],

            // Tenant-scoped: a brand from another company is not "exists".
            'brand_id' => ['nullable', 'integer', $this->tenantExists(Brand::class)],

            'minimum_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999'],

            // Tax is optional, but a half-set pair is a mis-pricing bug later.
            'tax_type' => ['nullable', Rule::in(TaxType::values())],
            'tax_value' => ['nullable', 'numeric', 'min:0'],

            'status' => ['nullable', 'string', 'max:20'],

            // --- Nested collections -------------------------------------------------
            'units' => [$required ? 'required' : 'sometimes', 'array', 'min:1'],
            'units.*.unit_id' => ['required', 'integer', $this->tenantExists(Unit::class)],
            'units.*.conversion_factor' => ['required', 'numeric', 'gt:0'],
            'units.*.selling_price' => ['required', 'integer', 'min:0'],
            'units.*.is_base' => ['sometimes', 'boolean'],

            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', $this->tenantExists(Category::class)],

            'supplier_terms' => ['sometimes', 'array'],
            'supplier_terms.*.supplier_id' => ['required', 'integer', $this->tenantExists(Supplier::class)],
            'supplier_terms.*.purchase_unit_id' => ['required', 'integer', $this->tenantExists(Unit::class)],
            'supplier_terms.*.purchase_price' => ['required', 'integer', 'min:0'],

            'attribute_value_ids' => ['sometimes', 'array'],
            'attribute_value_ids.*' => ['integer', $this->tenantExists(AttributeValue::class)],

            'variants' => ['sometimes', 'array'],
            'variants.*' => ['array'],
            'variants.*.*' => ['integer', $this->tenantExists(AttributeValue::class)],
        ];
    }

    /**
     * Reject a client-supplied company_id outright.
     *
     * It is ignored anyway (the field is not fillable), but silently dropping it
     * would leave a developer believing they had set the owner. Refusing it
     * makes the attempted spoof visible instead of invisible.
     */
    private function rejectCompanyIdSpoofing(Validator $v): void
    {
        if ($this->filled('company_id')) {
            $v->errors()->add(
                'company_id',
                'company_id is determined by the authenticated tenant and cannot be set.'
            );
        }
    }

    /**
     * @return array<int, callable>
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->rejectCompanyIdSpoofing($v);
            $this->validateTaxPair($v);
            $this->validateUnits($v);
            $this->validateVariableConfiguration($v);
            $this->validateUniqueIdentifiers($v);
            $this->validateSupplierTerms($v);
            $this->validateVariantCombinations($v);
        });
    }

    /**
     * Tax is all-or-nothing: a type without a value (or vice versa) is a
     * half-configured product that would silently mis-price later.
     */
    private function validateTaxPair(Validator $v): void
    {
        $type = $this->input('tax_type');
        $value = $this->input('tax_value');

        if ($type !== null && $value === null) {
            $v->errors()->add('tax_value', 'A tax value is required when a tax type is set.');
        }

        if ($type === null && $value !== null) {
            $v->errors()->add('tax_type', 'A tax type is required when a tax value is set.');
        }
    }

    /**
     * Product-level unit invariants.
     */
    private function validateUnits(Validator $v): void
    {
        if (! $this->filled('units')) {
            return;
        }

        $units = (array) $this->input('units', []);

        if (collect($units)->where('is_base', true)->count() !== 1) {
            $v->errors()->add('units', 'Exactly one unit must be marked as the base unit.');
        }

        if (collect($units)->pluck('unit_id')->duplicates()->isNotEmpty()) {
            $v->errors()->add('units', 'The same unit cannot be listed twice for one product.');
        }

        // The base unit defines what a quantity of 1 means, so its factor is 1.
        foreach ($units as $index => $unit) {
            if (($unit['is_base'] ?? false) && (float) ($unit['conversion_factor'] ?? 1) !== 1.0) {
                $v->errors()->add("units.{$index}.conversion_factor", 'The base unit conversion factor must be 1.');
            }
        }
    }

    /**
     * A variable product must declare attribute values; a simple product must
     * not carry variants.
     */
    private function validateVariableConfiguration(Validator $v): void
    {
        $type = $this->input('product_type')
            ?? $this->productBeingUpdated()?->product_type?->value;

        if ($type === ProductType::Variable->value && ! $this->filled('attribute_value_ids')) {
            $v->errors()->add('attribute_value_ids', 'A variable product requires at least one attribute value.');
        }

        if ($type !== ProductType::Variable->value && $this->filled('variants')) {
            $v->errors()->add('variants', 'Only variable products may define variants.');
        }
    }

    /**
     * SKU and barcode are unique WITHIN the tenant, never globally.
     */
    private function validateUniqueIdentifiers(Validator $v): void
    {
        $product = $this->productBeingUpdated();
        $companyId = $this->activeCompanyId();

        foreach (['sku', 'barcode'] as $field) {
            $value = $this->input($field);

            if ($value === null || $value === '') {
                continue;
            }

            $exists = Product::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where($field, $value)
                ->when($product !== null, fn ($q) => $q->whereKeyNot($product->id))
                ->exists();

            if ($exists) {
                $v->errors()->add($field, "This {$field} is already used by another product.");
            }
        }
    }

    /**
     * A supplier's purchase unit must be one of THIS product's units, and the
     * same supplier cannot be attached twice.
     */
    private function validateSupplierTerms(Validator $v): void
    {
        if (! $this->filled('supplier_terms')) {
            return;
        }

        $terms = (array) $this->input('supplier_terms', []);

        if (collect($terms)->pluck('supplier_id')->duplicates()->isNotEmpty()) {
            $v->errors()->add('supplier_terms', 'The same supplier cannot be attached twice.');
        }

        $allowedUnitIds = $this->allowedUnitIds();

        foreach ($terms as $index => $term) {
            if ($allowedUnitIds !== null && ! in_array((int) ($term['purchase_unit_id'] ?? 0), $allowedUnitIds, true)) {
                $v->errors()->add(
                    "supplier_terms.{$index}.purchase_unit_id",
                    'The purchase unit must be one of this product\'s units.'
                );
            }
        }
    }

    /**
     * Unit ids this request declares, falling back to those already stored on
     * the product being updated.
     *
     * @return array<int, int>|null
     */
    private function allowedUnitIds(): ?array
    {
        if ($this->filled('units')) {
            return collect((array) $this->input('units', []))
                ->pluck('unit_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        $product = $this->productBeingUpdated();

        return $product === null
            ? null
            : $product->units()->pluck('unit_id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * No two variants may describe the same set of attribute values.
     *
     * The key is order-independent, so [3,7] and [7,3] are the same combination
     * and the second must be rejected.
     */
    private function validateVariantCombinations(Validator $v): void
    {
        if (! $this->filled('variants')) {
            return;
        }

        $keys = collect((array) $this->input('variants', []))
            ->map(function (array $valueIds): string {
                $ids = array_values(array_unique(array_map('intval', $valueIds)));
                sort($ids);

                return implode('-', $ids);
            });

        if ($keys->duplicates()->isNotEmpty()) {
            $v->errors()->add('variants', 'Duplicate variant combinations are not allowed.');
        }

        foreach ((array) $this->input('variants', []) as $index => $valueIds) {
            if (count(array_unique((array) $valueIds)) < 2) {
                $v->errors()->add("variants.{$index}", 'A variant needs at least two distinct attribute values.');
            }
        }
    }
}
