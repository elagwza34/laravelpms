<?php

namespace Database\Factories;

use App\Enums\CompanyStatus;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => CompanyStatus::Active->value,
        ];
    }

    public function trial(): static
    {
        return $this->state(fn (): array => ['status' => CompanyStatus::Trial->value]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['status' => CompanyStatus::Expired->value]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => CompanyStatus::Suspended->value]);
    }
}
