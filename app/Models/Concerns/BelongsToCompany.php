<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\Tenancy\TenancyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as owned by exactly one tenant and enforces that ownership.
 *
 * Applying this trait does two things, and both are required for isolation:
 *
 *  1. Reads are filtered by a global scope tied to the active tenant, so a query
 *     that forgets a where() clause still cannot leak another company's rows.
 *  2. Writes stamp company_id from the server-side tenant context, so a client
 *     cannot choose which company a new record belongs to by sending a payload.
 *
 * @property int $company_id
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        /*
         * `static::class` rather than `$this`: this closure is bound to the
         * model instance, not to the trait, so `$this` is undefined here. Using
         * the class name is what lets getCompanyColumn() be called correctly.
         */
        static::creating(function ($model): void {
            $tenancy = app(TenancyContext::class);

            $column = method_exists($model, 'getCompanyColumn')
                ? $model->getCompanyColumn()
                : (string) config('tenancy.column', 'company_id');

            /*
             * A tenant-owned record may only be created while a tenant is
             * active. Anything else (seeding, console commands) must set the
             * company explicitly, which keeps the intent visible.
             */
            if ($tenancy->has() && $model->getAttribute($column) === null) {
                $model->setAttribute($column, $tenancy->company()?->getKey());
            }
        });
    }

    /**
     * The owning tenant.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, $this->getCompanyColumn());
    }

    /**
     * The foreign key that identifies the owning tenant.
     */
    public function getCompanyColumn(): string
    {
        return defined(static::class.'::COMPANY_COLUMN')
            ? constant(static::class.'::COMPANY_COLUMN')
            : (string) config('tenancy.column', 'company_id');
    }

    /**
     * Escape hatch for platform staff and console commands that legitimately
     * need cross-tenant reads (e.g. a platform-wide report).
     *
     * Must always be paired with an explicit permission check by the caller.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAcrossCompanies(Builder $query): Builder
    {
        return $query->withoutGlobalScope(CompanyScope::class);
    }
}
