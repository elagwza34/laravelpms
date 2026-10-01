<?php

namespace App\Http\Requests\Api\MasterData;

use App\Enums\RecordStatus;
use App\Http\Requests\Api\ApiFormRequest;
use App\Models\Supplier;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Supplier validation. Suppliers are tenant-owned; there is no default supplier
 * concept anywhere in this request.
 */
class SupplierRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('suppliers', 'slug')
                    ->where('company_id', $this->activeCompanyId())
                    ->ignore($supplier instanceof Supplier ? $supplier->id : null),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:2000'],
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
        });
    }
}
