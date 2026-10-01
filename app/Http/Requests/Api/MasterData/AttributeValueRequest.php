<?php

namespace App\Http\Requests\Api\MasterData;

use App\Enums\RecordStatus;
use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Attribute value validation.
 *
 * The parent attribute is resolved through the tenant-scoped model, so a value
 * can never be attached to another company's attribute.
 */
class AttributeValueRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(RecordStatus::values())],

            /*
             * The parent attribute is normally taken from the route
             * ({company}/{attribute}/values). The explicit attribute_id is only
             * honoured when it belongs to the ACTIVE TENANT, never blindly.
             */
            'attribute_id' => [
                'sometimes', 'integer',
                $this->tenantExists(Attribute::class),
            ],
        ];
    }

    /**
     * The route's attribute id, resolved within the active tenant.
     */
    public function attributeId(): ?int
    {
        $id = $this->route('attribute');

        return is_numeric($id) ? (int) $id : null;
    }
}
