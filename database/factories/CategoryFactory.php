<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Category;
use App\Models\Company;
use App\Support\Tenancy\TenancyContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'company_id' => TenancyContext::currentOrNull()?->getKey()
                ?? Company::factory()->create()->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'parent_id' => null,
            'status' => RecordStatus::Active->value,
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn (): array => [
            'company_id' => $parent->company_id,
            'parent_id' => $parent->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => RecordStatus::Inactive->value]);
    }
}
