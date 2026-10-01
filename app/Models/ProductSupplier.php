<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-supplier purchasing terms for one product.
 *
 * This table is the reason products carry no cost column: the same product may
 * cost 300 EGP per Carton from one supplier and 15 EGP per Piece from another,
 * and that difference is a property of the product/supplier pairing.
 *
 * There is no is_default flag by design — the PMS has no default supplier.
 *
 * @property int $id
 * @property int $product_id
 * @property int $supplier_id
 * @property int $purchase_unit_id
 * @property int $purchase_price minor units (piastres)
 */
class ProductSupplier extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'supplier_id',
        'purchase_unit_id',
        'purchase_price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_price' => 'integer',
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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * The unit this supplier sells in. Always one of the product's own units.
     *
     * @return BelongsTo<Unit, $this>
     */
    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }
}
