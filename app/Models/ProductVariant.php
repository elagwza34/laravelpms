<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasRecordStatus;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One option combination of a VARIABLE product (Black/M, Black/L, ...).
 *
 * A variant is NOT an independent stock item. It deliberately has no sku,
 * barcode, price, cost, tax or stock column: all commercial and inventory data
 * belongs to the parent product, which stays a single inventory item.
 *
 * combination_key is derived from the sorted attribute value ids, so the same
 * set of values always produces the same key regardless of the order the client
 * sent them in.
 *
 * @property int $id
 * @property int $company_id
 * @property int $product_id
 * @property string $combination_key
 * @property RecordStatus $status
 */
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use BelongsToCompany, HasFactory, HasRecordStatus, SoftDeletes;

    /**
     * @var list<string>
     *
     * company_id is stamped from the product by the service layer and is
     * guarded against mass assignment here; the composite foreign key
     * (product_id, company_id) makes a mismatch impossible at the database too.
     */
    protected $fillable = [
        'product_id',
        'combination_key',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $guarded = [
        'company_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
        ];
    }

    /**
     * Build the deterministic key for a set of attribute value ids.
     *
     * Sorting is what makes the key canonical: [12, 45] and [45, 12] describe
     * the same combination and must collide rather than create two variants.
     *
     * @param  array<int, int>  $attributeValueIds
     */
    public static function combinationKeyFor(array $attributeValueIds): string
    {
        $ids = array_values(array_unique(array_map('intval', $attributeValueIds)));
        sort($ids);

        return implode('-', $ids);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsToMany<AttributeValue, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'variant_attribute_values');
    }
}
