<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialises a product for the API.
 *
 * company_id is intentionally exposed. It is not a security input — nothing is
 * authorised from it — and returning it lets the frontend render "which company
 * am I looking at" without guessing.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,

            /*
             * Enum-backed attributes are read defensively. A raw attribute that
             * fails to cast (unexpected value from the database) must produce a
             * usable null in the payload rather than a 500 that hides the real
             * problem from the client.
             */
            'product_type' => $this->product_type?->value,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'image' => $this->image,
            'minimum_stock' => $this->minimum_stock,
            'tax_type' => $this->tax_type?->value,
            'tax_value' => $this->tax_value,
            'status' => $this->status?->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'brand' => $this->whenLoaded('brand', fn (): ?array => $this->brand === null ? null : [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
            ]),

            'categories' => CategoryResource::collection($this->whenLoaded('categories')),

            // Each entry carries its own selling price and conversion factor.
            'units' => ProductUnitResource::collection($this->whenLoaded('units')),

            'supplier_terms' => ProductSupplierResource::collection($this->whenLoaded('supplierTerms')),

            // Variants carry option data only — no price, stock, sku or barcode.
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
