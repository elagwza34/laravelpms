<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Enums\RecordStatus;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Product;
use App\Support\Tenancy\TenancyContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'company_id' => TenancyContext::currentOrNull()?->getKey()
                ?? Company::factory()->create()->id,
            'name' => $name,
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'barcode' => (string) fake()->unique()->numerify('62#########'),
            'product_type' => ProductType::Simple->value,
            'short_description' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'image' => null,
            'brand_id' => null,
            'minimum_stock' => fake()->numberBetween(0, 50),
            'tax_type' => null,
            'tax_value' => null,
            'status' => RecordStatus::Active->value,
        ];
    }

    public function variable(): static
    {
        return $this->state(fn (): array => ['product_type' => ProductType::Variable->value]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => RecordStatus::Inactive->value]);
    }

    public function withBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['brand_id' => $brand->id]);
    }

    public function withTax(string $type, float $value): static
    {
        return $this->state(fn (): array => ['tax_type' => $type, 'tax_value' => $value]);
    }
}
