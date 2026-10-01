<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\RecordStatus;
use App\Enums\TaxType;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasRecordStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A product: the single commercial and inventory entity of the PMS.
 *
 * SCOPE — exactly two product types exist, simple and variable. Service,
 * bundle and composite are not part of the model.
 *
 * VARIABLE PRODUCTS are still ONE inventory item. A ProductVariant only records
 * which attribute values were chosen; all price, stock, SKU and barcode data
 * lives here on the product.
 *
 * company_id is deliberately NOT mass assignable: it is stamped from the
 * authenticated tenant context, so a client can never move a product to
 * another company by sending company_id in the payload.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string|null $sku
 * @property string|null $barcode
 * @property ProductType $product_type
 * @property string|null $short_description
 * @property string|null $description
 * @property string|null $image
 * @property int|null $brand_id
 * @property string|null $minimum_stock
 * @property TaxType|null $tax_type
 * @property string|null $tax_value
 * @property RecordStatus $status
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToCompany, HasFactory, HasRecordStatus, SoftDeletes;

    /**
     * @var list<string>
     *
     * company_id is absent by design — see the class docblock.
     */
    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'product_type',
        'short_description',
        'description',
        'image',
        'brand_id',
        'minimum_stock',
        'tax_type',
        'tax_value',
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
            'product_type' => ProductType::class,
            'status' => RecordStatus::class,
            'tax_type' => TaxType::class,
            'minimum_stock' => 'decimal:3',
            'tax_value' => 'decimal:2',
        ];
    }

    /**
     * Optional: at most one brand per product.
     *
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        // Explicit table name: Laravel would otherwise derive "category_product".
        return $this->belongsToMany(Category::class, 'product_category');
    }

    /**
     * Suppliers this product can be purchased from.
     *
     * Reached THROUGH product_suppliers rather than a bare pivot, because the
     * pairing carries the purchase unit and price. A plain many-to-many would
     * silently drop exactly the data that makes the relation useful.
     *
     * @return HasManyThrough<Supplier, ProductSupplier, $this>
     */
    public function suppliers(): HasManyThrough
    {
        return $this->hasManyThrough(
            Supplier::class,
            ProductSupplier::class,
            'product_id', // on product_suppliers
            'id',         // on suppliers
            'id',         // on products
            'id'          // on suppliers (local key)
        );
    }

    /**
     * The units this product is sold/purchased in, including its base unit.
     *
     * @return HasMany<ProductUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    /**
     * Per-supplier purchasing terms.
     *
     * @return HasMany<ProductSupplier, $this>
     */
    public function supplierTerms(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function isVariable(): bool
    {
        return $this->product_type === ProductType::Variable;
    }

    /**
     * The product's base unit row — the one every stock quantity is stored in.
     *
     * Null only for a malformed product; creation validation guarantees exactly
     * one base row exists.
     */
    public function baseUnit(): ?ProductUnit
    {
        return $this->relationLoaded('units')
            ? $this->units->firstWhere('is_base', true)
            : $this->units()->where('is_base', true)->first();
    }

    /**
     * Whether stock has fallen to the reorder threshold.
     *
     * Phase 2 only stores minimum_stock and never moves stock, so nothing is
     * currently "low"; the rule lives here so the inventory phase inherits one
     * definition instead of re-deriving it.
     */
    public function isLowStock(float $currentQuantity): bool
    {
        return $this->minimum_stock !== null
            && $currentQuantity <= (float) $this->minimum_stock;
    }

    /**
     * Free-text search across name, SKU and barcode.
     *
     * Scoped to the tenant by CompanyScope, so a search can never surface
     * another company's rows.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        /*
         * Escape LIKE wildcards, otherwise searching for "100%" matches every
         * row. The ESCAPE clause is declared explicitly because MySQL's default
         * escape character differs between engines.
         */
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('barcode', 'like', $like);
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return $type === null ? $query : $query->where('product_type', $type);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfBrand(Builder $query, ?int $brandId): Builder
    {
        return $brandId === null ? $query : $query->where('brand_id', $brandId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInCategory(Builder $query, ?int $categoryId): Builder
    {
        return $categoryId === null
            ? $query
            : $query->whereHas(
                'categories',
                fn (Builder $relation) => $relation->where('categories.id', $categoryId)
            );
    }

    /**
     * Relations worth eager loading for a list view.
     *
     * Centralised so controllers cannot forget one and quietly reintroduce an
     * N+1.
     *
     * @return array<int, string>
     */
    public static function listRelations(): array
    {
        return ['brand', 'units.unit'];
    }

    /**
     * @return array<int, string>
     */
    public static function detailRelations(): array
    {
        return [
            'brand',
            'categories',
            'units.unit',
            'supplierTerms.supplier',
            'supplierTerms.purchaseUnit',
            'variants.attributeValues.attribute',
        ];
    }
}
