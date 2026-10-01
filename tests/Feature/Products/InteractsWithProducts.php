<?php

namespace Tests\Feature\Products;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;
use Tests\InteractsWithTenants;

/**
 * Fixtures shared by the Phase 2 product tests.
 *
 * Every helper builds records for a SPECIFIC company while establishing the
 * matching tenant context first, because the models are tenant-scoped. That is
 * what lets the tests create genuine Company B data and then attempt to reach it
 * from Company A — exactly the shape of a real IDOR attempt.
 */
trait InteractsWithProducts
{
    use InteractsWithTenants;

    protected Company $acme;

    protected Company $globex;

    protected function seedTwoCompanies(): void
    {
        $this->seedPermissions();
        $this->acme = $this->makeCompany('acme', 'Acme');
        $this->globex = $this->makeCompany('globex', 'Globex');
    }

    /**
     * A member holding every tenant permission.
     */
    protected function ownerOf(Company $company, string $roleSlug = 'owner'): User
    {
        return $this->makeMember(
            $company,
            $company->roles()->where('slug', $roleSlug)->firstOrFail(),
            $roleSlug.'-'.uniqid().'@'.$company->slug.'.test'
        );
    }

    /**
     * Create a unit owned by the given company.
     */
    protected function unitFor(Company $company, string $name = 'Piece'): Unit
    {
        $this->withTenancyFor($company);

        $unit = Unit::factory()->create([
            'company_id' => $company->id,
            'name' => $name,
        ]);

        $this->withoutTenancy();

        return $unit;
    }

    /**
     * Create a brand owned by the given company.
     */
    protected function brandFor(Company $company, string $name = 'Acme Brand'): Brand
    {
        $this->withTenancyFor($company);

        $brand = Brand::factory()->create([
            'company_id' => $company->id,
            'name' => $name,
        ]);

        $this->withoutTenancy();

        return $brand;
    }

    /**
     * Create a category owned by the given company.
     */
    protected function categoryFor(Company $company, string $name = 'Electronics'): Category
    {
        $this->withTenancyFor($company);

        $category = Category::factory()->create([
            'company_id' => $company->id,
            'name' => $name,
        ]);

        $this->withoutTenancy();

        return $category;
    }

    /**
     * Create a supplier owned by the given company.
     */
    protected function supplierFor(Company $company, string $name = 'Supplier'): Supplier
    {
        $this->withTenancyFor($company);

        $supplier = Supplier::factory()->create([
            'company_id' => $company->id,
            'name' => $name,
        ]);

        $this->withoutTenancy();

        return $supplier;
    }

    /**
     * Create an attribute with its values, owned by the given company.
     *
     * @param  array<int, string>  $values
     * @return array{attribute: Attribute, values: Collection<int, AttributeValue>}
     */
    protected function attributeFor(Company $company, string $name, array $values): array
    {
        $this->withTenancyFor($company);

        $attribute = Attribute::factory()->create([
            'company_id' => $company->id,
            'name' => $name,
        ]);

        $created = collect($values)->map(fn (string $value) => $attribute->values()->create([
            'company_id' => $company->id,
            'value' => $value,
        ]));

        $this->withoutTenancy();

        return ['attribute' => $attribute, 'values' => $created];
    }

    /**
     * Create a product with a base unit, owned by the given company.
     */
    protected function productFor(Company $company, string $name, ?string $sku = null): Product
    {
        $this->withTenancyFor($company);

        $unit = Unit::factory()->create([
            'company_id' => $company->id,
            'name' => 'Base Unit '.uniqid(),
        ]);

        $product = Product::factory()->create([
            'company_id' => $company->id,
            'name' => $name,
            'sku' => $sku ?? ('SKU-'.uniqid()),
        ]);

        $product->units()->create([
            'unit_id' => $unit->id,
            'conversion_factor' => 1,
            'selling_price' => 1000,
            'is_base' => true,
        ]);

        $this->withoutTenancy();

        return $product;
    }

    /**
     * A minimal valid product-create payload, merged with overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function productPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Product',
            'sku' => 'SKU-'.uniqid(),
            'product_type' => 'simple',
            'units' => [],
        ], $overrides);
    }

    /**
     * A unit entry for a product payload.
     *
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

    /**
     * Create a product through the API and return the persisted model.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createProduct(array $overrides = [], ?Company $company = null): Product
    {
        $company ??= $this->acme;

        $this->actingAsToken($this->ownerOf($company))
            ->postJson("/api/v1/{$company->slug}/products", $this->productPayload($overrides))
            ->assertCreated();

        return $this->latestProduct($company);
    }

    /**
     * The most recently created product for a company.
     */
    protected function latestProduct(?Company $company = null): Product
    {
        $company ??= $this->acme;

        return Product::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->latest('id')
            ->firstOrFail();
    }
}
