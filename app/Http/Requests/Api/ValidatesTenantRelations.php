<?php

namespace App\Http\Requests\Api;

use App\Support\Tenancy\TenancyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Provides tenant-aware validation helpers to every API request.
 *
 * THE POINT
 * ---------
 * `Rule::exists('brands', 'id')` on its own would happily accept another
 * company's brand id, because the exists() query runs outside Eloquent and
 * therefore ignores CompanyScope. That is precisely the IDOR the spec forbids.
 *
 * tenantExists() closes the hole by constraining the lookup with
 * company_id = <active tenant>, so a foreign record is indistinguishable from a
 * nonexistent one.
 */
trait ValidatesTenantRelations
{
    /**
     * An exists() rule restricted to the active tenant.
     *
     * The constraint is written against the `company_id` COLUMN explicitly —
     * constraining the checked column instead would compare id to a company id,
     * which silently matches nothing (or worse, something).
     *
     * @param  class-string<Model>  $model
     */
    protected function tenantExists(string $model, string $column = 'id'): Exists
    {
        return Rule::exists($model, $column)
            ->where(fn ($query) => $query->where('company_id', $this->activeCompanyId()));
    }

    /**
     * The active tenant id, or null when no tenant is resolved.
     */
    protected function activeCompanyId(): ?int
    {
        return app(TenancyContext::class)->id();
    }

    /**
     * Whether the given model carries its own company_id column.
     *
     * Central tables (companies, permissions, roles) are not tenant-scoped and
     * must not be filtered by company.
     *
     * @param  class-string<Model>  $model
     */
    protected function isTenantScoped(string $model): bool
    {
        return in_array(
            'company_id',
            Schema::getColumnListing((new $model)->getTable()),
            true
        );
    }

    /**
     * An exists() rule that applies company scoping only to tenant-scoped
     * tables, so callers can validate against either kind uniformly.
     *
     * @param  class-string<Model>  $model
     */
    protected function existsForModel(string $model, string $column = 'id'): Exists
    {
        return $this->isTenantScoped($model)
            ? $this->tenantExists($model, $column)
            : Rule::exists($model, $column);
    }

    /**
     * Fetch ids of the active tenant, for use in `in` rules.
     *
     * @param  class-string<Model>  $model
     * @return Collection<int, int>
     */
    protected function tenantIds(string $model): Collection
    {
        return $model::query()->pluck('id');
    }
}
