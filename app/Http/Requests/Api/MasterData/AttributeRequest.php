<?php

namespace App\Http\Requests\Api\MasterData;

use App\Enums\RecordStatus;
use App\Http\Requests\Api\ApiFormRequest;
use App\Models\Attribute;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Attribute validation.
 *
 * Attributes are company-level only: there are no global attributes, so the
 * slug is unique per tenant and can never be written by another company.
 *
 * `values` is accepted inline so an attribute and its options can be created in
 * one call; the values themselves are created by the controller, which stamps
 * company_id from the parent.
 */
class AttributeRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $attribute = $this->route('attribute');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('attributes', 'slug')
                    ->where('company_id', $this->activeCompanyId())
                    ->ignore($attribute instanceof Attribute ? $attribute->id : null),
            ],
            'status' => ['nullable', Rule::in(RecordStatus::values())],

            'values' => ['sometimes', 'array'],
            'values.*' => ['required', 'string', 'max:255'],
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

            if ($this->filled('values')) {
                $values = collect((array) $this->input('values', []))
                    ->map(fn ($value): string => trim((string) $value));

                if ($values->contains('')) {
                    $v->errors()->add('values', 'Attribute values cannot be empty.');
                }

                if ($values->duplicates()->isNotEmpty()) {
                    $v->errors()->add('values', 'Attribute values must be unique.');
                }
            }
        });
    }
}
