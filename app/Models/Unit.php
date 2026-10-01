<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasRecordStatus;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tenant-owned unit of measure (Piece, Box, Kg, Liter ...).
 *
 * A unit carries no conversion rate on its own: "1 Carton = 12 Pieces" is a
 * statement about one product, so the rate lives on product_units.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string|null $abbreviation
 * @property RecordStatus $status
 */
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use BelongsToCompany, HasFactory, HasRecordStatus, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'abbreviation',
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

    /**
     * Every product that offers this unit.
     *
     * @return HasMany<ProductUnit, $this>
     */
    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }
}
