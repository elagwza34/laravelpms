<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            // Stamped from the product so the composite FK always agrees.
            'company_id' => fn (array $attributes): int => Product::query()
                ->whereKey($attributes['product_id'])
                ->value('company_id'),
            'combination_key' => (string) fake()->unique()->numberBetween(1, 999999),
            'status' => RecordStatus::Active->value,
        ];
    }

    public function forProduct(Product $product, string $combinationKey): static
    {
        return $this->state(fn (): array => [
            'product_id' => $product->id,
            'company_id' => $product->company_id,
            'combination_key' => $combinationKey,
        ]);
    }
}
