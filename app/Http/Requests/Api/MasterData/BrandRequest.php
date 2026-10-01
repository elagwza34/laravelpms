<?php

namespace App\Http\Requests\Api\MasterData;

use App\Enums\RecordStatus;
use App\Http\Requests\Api\ApiFormRequest;
use App\Models\Brand;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Brand validation.
 *
 * The slug is unique WITHIN the tenant: two companies may each have a brand
 * called "Apple". On update the record's own slug must be excluded, otherwise a
 * brand could never be renamed without changing its slug.
 */
class BrandRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $brand = $this->route('brand');
        $brandId = $brand instanceof Brand ? $brand->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('brands', 'slug')
                    ->where('company_id', $this->activeCompanyId())
                    ->ignore($brandId),
            ],
            'logo' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(RecordStatus::values())],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            // company_id in the payload is never honoured; reject it loudly so a
            // client developer notices rather than silently losing the field.
            if ($this->filled('company_id')) {
                $v->errors()->add('company_id', 'company_id is determined by the authenticated tenant and cannot be set.');
            }
        });
    }
}
