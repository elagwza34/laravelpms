<?php

namespace App\Http\Resources;

use App\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttributeValue
 */
class AttributeValueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'attribute_id' => $this->attribute_id,
            'value' => $this->value,
            'slug' => $this->slug,
            'status' => $this->status?->value,

            // Lets the UI group values under their attribute name.
            'attribute' => $this->whenLoaded('attribute', fn (): ?array => $this->attribute === null ? null : [
                'id' => $this->attribute->id,
                'name' => $this->attribute->name,
            ]),
        ];
    }
}
