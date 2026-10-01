<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Attribute;
use App\Models\Company;
use App\Support\Tenancy\TenancyContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'company_id' => TenancyContext::currentOrNull()?->getKey()
                ?? Company::factory()->create()->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'status' => RecordStatus::Active->value,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => RecordStatus::Inactive->value]);
    }
}
