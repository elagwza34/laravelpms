<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasRecordStatus;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A tenant-owned brand. Optional for a product: at most one per product, many
 * products per brand.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $slug
 * @property RecordStatus $status
 */
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use BelongsToCompany, HasFactory, HasRecordStatus, SoftDeletes;

    /**
     * @var list<string>
     *
     * company_id is intentionally absent: it is stamped from the authenticated
     * tenant context by the BelongsToCompany trait, so a client can never
     * choose the owner by sending it in the payload.
     */
    protected $fillable = [
        'name',
        'slug',
        'logo',
        'description',
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
        static::saving(function (self $brand): void {
            if ($brand->isDirty('name') && blank($brand->slug)) {
                $brand->slug = Str::slug($brand->name);
            }
        });
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
