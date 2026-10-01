<?php

namespace App\Http\Resources;

use App\Models\ProductSupplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Purchasing terms for one supplier.
 *
 * This is the source of cost: the same product may be bought from several
 * suppliers at different prices and in different units.
 *
 * @mixin ProductSupplier
 */
class ProductSupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'purchase_unit_id' => $this->purchase_unit_id,
            'purchase_price' => $this->purchase_price,
            'purchase_price_decimal' => $this->purchase_price / 100,

            'supplier' => $this->whenLoaded('supplier', fn (): ?array => $this->supplier === null ? null : [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'slug' => $this->supplier->slug,
            ]),

            'purchase_unit' => $this->whenLoaded('purchaseUnit', fn (): ?array => $this->purchaseUnit === null ? null : [
                'id' => $this->purchaseUnit->id,
                'name' => $this->purchaseUnit->name,
            ]),
        ];
    }
}
