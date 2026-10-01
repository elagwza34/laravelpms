<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasRecordStatus;
use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A tenant-owned product attribute (Color, Size, Fabric ...).
 *
 * Attributes are COMPANY-LEVEL ONLY: there are no global attributes, so a
 * product can only ever be described using attributes its own company owns.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $slug
 * @property RecordStatus $status
 */
class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use BelongsToCompany, HasFactory, HasRecordStatus, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
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
        static::saving(function (self $attribute): void {
            if ($attribute->isDirty('name') && blank($attribute->slug)) {
                $attribute->slug = Str::slug($attribute->name);
            }
        });
    }

    /**
     * @return HasMany<AttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }
}
