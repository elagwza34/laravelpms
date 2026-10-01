<?php

namespace App\Http\Resources;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One option combination of a variable product.
 *
 * Note what is absent: no sku, barcode, price, cost, tax or stock. All of that
 * belongs to the parent product, which remains a single inventory item.
 *
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'combination_key' => $this->combination_key,
            'status' => $this->status?->value,

            // The chosen attribute values, with their attribute name attached so
            // the UI can render "Black / M" without a second lookup.
            'attribute_values' => $this->whenLoaded('attributeValues', fn () => $this->attributeValues->map(fn ($value): array => [
                'id' => $value->id,
                'value' => $value->value,
                'attribute' => $value->relationLoaded('attribute') ? [
                    'id' => $value->attribute->id,
                    'name' => $value->attribute->name,
                ] : null,
            ])->values()),
        ];
    }
}
