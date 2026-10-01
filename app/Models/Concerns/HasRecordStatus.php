<?php

namespace App\Models\Concerns;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Active/inactive state shared by the tenant-owned Phase 2 records
 * (brands, categories, units, attributes, attribute values, suppliers,
 * products, variants).
 *
 * Deliberately does NOT include SoftDeletes: the tenancy layer already handles
 * that, and mixing the two here would silently change delete semantics.
 *
 * @mixin Model
 */
trait HasRecordStatus
{
    /**
     * Records that may be picked for NEW operations.
     *
     * Inactive records stay queryable and visible in history; they are only
     * excluded from pickers, which is why this is a scope rather than a filter.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Active->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Inactive->value);
    }

    public function isActive(): bool
    {
        return $this->status === RecordStatus::Active;
    }

    /**
     * Deactivate without deleting: the record stays in history but can no
     * longer be selected for new operations.
     */
    public function markInactive(): bool
    {
        return $this->forceFill(['status' => RecordStatus::Inactive->value])->save();
    }

    public function markActive(): bool
    {
        return $this->forceFill(['status' => RecordStatus::Active->value])->save();
    }
}

/**
 * Convenience re-export so models can import a single trait when they need both
 * tenancy and status. Order matters: BelongsToCompany registers the global
 * scope, this one only adds helpers.
 *
 * @mixin Model
 */
trait HasTenantState
{
    use BelongsToCompany;
    use HasRecordStatus;
}
