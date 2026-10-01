<?php

namespace App\Http\Resources;

use App\Models\ProductUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One unit a product is sold in.
 *
 * selling_price is integer minor units in the database; `selling_price_decimal`
 * exposes the same value pre-divided so a client never has to guess the scale.
 *
 * @mixin ProductUnit
 */
class ProductUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'unit_id' => $this->unit_id,
            'conversion_factor' => (float) $this->conversion_factor,
            'selling_price' => $this->selling_price,
            'selling_price_decimal' => $this->selling_price / 100,
            'is_base' => $this->is_base,

            'unit' => $this->whenLoaded('unit', fn (): ?array => $this->unit === null ? null : [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
                'abbreviation' => $this->unit->abbreviation,
            ]),
        ];
    }
}
