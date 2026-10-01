<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasRecordStatus;
use Database\Factories\AttributeValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One allowed value of an attribute (Color -> Black, White, Red).
 *
 * company_id duplicates the parent attribute's company_id on purpose so this
 * model obeys the same CompanyScope as every other tenant table. A composite
 * foreign key (attribute_id, company_id) makes the duplication impossible to
 * violate, so a value can never belong to another company's attribute.
 *
 * @property int $id
 * @property int $company_id
 * @property int $attribute_id
 * @property string $value
 * @property RecordStatus $status
 */
class AttributeValue extends Model
{
    /** @use HasFactory<AttributeValueFactory> */
    use BelongsToCompany, HasFactory, HasRecordStatus, SoftDeletes;

    /**
     * @var list<string>
     *
     * attribute_id is listed so an attribute's values can be written in one
     * pass, but the relationship always supplies it from a tenant-scoped parent
     * — a request can name the attribute, never re-parent a value.
     *
     * company_id is deliberately absent: it is stamped from the authenticated
     * tenant context by BelongsToCompany, so a client can never move a value to
     * another company by sending it in the payload.
     */
    protected $fillable = [
        'attribute_id',
        'value',
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

    /**
     * @return BelongsTo<Attribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * Variants that use this value.
     *
     * @return BelongsToMany<ProductVariant, $this>
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'variant_attribute_values');
    }
}
