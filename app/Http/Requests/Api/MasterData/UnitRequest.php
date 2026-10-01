<?php

namespace App\Http\Requests\Api\MasterData;

use App\Enums\RecordStatus;
use App\Http\Requests\Api\ApiFormRequest;
use App\Models\Unit;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Unit validation. Unit names are unique within the tenant.
 */
class UnitRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unit = $this->route('unit');

        return [
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', Rule::in(RecordStatus::values())],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($this->filled('company_id')) {
                $v->errors()->add('company_id', 'company_id is determined by the authenticated tenant and cannot be set.');
            }

            $unit = $this->route('unit');

            if (! $unit instanceof Unit || ! $this->filled('name')) {
                return;
            }

            $duplicate = Unit::query()
                ->where('name', $this->input('name'))
                ->whereKeyNot($unit->id)
                ->exists();

            if ($duplicate) {
                $v->errors()->add('name', 'A unit with this name already exists.');
            }
        });
    }
}
