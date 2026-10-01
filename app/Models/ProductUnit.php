<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A unit a specific product is sold or purchased in.
 *
 * conversion_factor expresses how many BASE units one of this unit equals:
 * a Box with factor 12 means "1 Box = 12 Pieces".
 *
 * Exactly one row per product has is_base = true and a factor of 1. Inventory
 * is always denominated in that base unit, so any quantity entered in another
 * unit must be converted through this row before it touches stock.
 *
 * @property int $id
 * @property int $product_id
 * @property int $unit_id
 * @property string $conversion_factor
 * @property int $selling_price minor units (piastres)
 * @property bool $is_base
 */
class ProductUnit extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'unit_id',
        'conversion_factor',
        'selling_price',
        'is_base',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:4',
            'selling_price' => 'integer',
            'is_base' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Convert a quantity expressed in this unit into the product's base unit.
     *
     * Selling 2 Boxes of a product whose base unit is Piece with factor 12
     * yields 24 — the number that actually moves stock.
     */
    public function toBaseQuantity(float|int|string $quantity): float
    {
        return round(((float) $quantity) * (float) $this->conversion_factor, 4);
    }
}
