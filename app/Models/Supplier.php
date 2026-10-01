<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasRecordStatus;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A tenant-owned supplier.
 *
 * Suppliers are the source of purchasing cost: the same product may be bought
 * from several suppliers at different prices and in different units, which is
 * modelled on product_suppliers rather than as a single cost column.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property RecordStatus $status
 */
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use BelongsToCompany, HasFactory, HasRecordStatus, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'address',
        'status',
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

    protected static function booted(): void
    {
        static::saving(function (self $supplier): void {
            if ($supplier->isDirty('name') && blank($supplier->slug)) {
                $supplier->slug = Str::slug($supplier->name);
            }
        });
    }

    /**
     * Products this supplier provides.
     *
     * Reached through product_suppliers so the purchasing terms (unit and
     * price) travel with the relation instead of being dropped by a bare pivot.
     *
     * @return HasManyThrough<Product, ProductSupplier, $this>
     */
    public function products(): HasManyThrough
    {
        return $this->hasManyThrough(
            Product::class,
            ProductSupplier::class,
            'supplier_id',
            'id',
            'id',
            'id'
        );
    }
}
