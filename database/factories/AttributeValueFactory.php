<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeValue>
 */
class AttributeValueFactory extends Factory
{
    protected $model = AttributeValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            /*
             * company_id is copied from the parent attribute rather than from
             * the ambient tenant, because the composite foreign key requires the
             * two to agree exactly.
             */
            'attribute_id' => Attribute::factory(),
            'company_id' => fn (array $attributes): int => Attribute::query()
                ->whereKey($attributes['attribute_id'])
                ->value('company_id'),
            'value' => fake()->unique()->word(),
            'status' => RecordStatus::Active->value,
        ];
    }

    public function ofAttribute(Attribute $attribute): static
    {
        return $this->state(fn (): array => [
            'attribute_id' => $attribute->id,
            'company_id' => $attribute->company_id,
        ]);
    }
}
