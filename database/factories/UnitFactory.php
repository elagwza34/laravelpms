<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\Unit;
use App\Support\Tenancy\TenancyContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Piece', 'Box', 'Carton', 'Kg', 'Gram', 'Liter', 'Meter',
        ]).'-'.fake()->unique()->numberBetween(1, 999999);

        return [
            'company_id' => TenancyContext::currentOrNull()?->getKey()
                ?? Company::factory()->create()->id,
            'name' => $name,
            'abbreviation' => fake()->lexify('??'),
            'status' => RecordStatus::Active->value,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => RecordStatus::Inactive->value]);
    }
}
