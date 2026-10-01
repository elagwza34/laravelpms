<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Brand;
use App\Models\Company;
use App\Support\Tenancy\TenancyContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            /*
             * Owned by the active tenant when there is one, so records built
             * inside a tenant request automatically belong to that company.
             */
            'company_id' => TenancyContext::currentOrNull()?->getKey()
                ?? Company::factory()->create()->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => fake()->sentence(),
            'status' => RecordStatus::Active->value,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => RecordStatus::Inactive->value]);
    }
}
