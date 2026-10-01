<?php

namespace App\Models\Concerns;

use App\Support\Tenancy\TenancyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global query scope that restricts a model to the active tenant.
 *
 * This is the safety net for tenant isolation. Even if a controller forgets an
 * explicit where clause, the generated SQL still carries "company_id = ?".
 *
 * When no tenant is active the scope deliberately does NOT filter. That
 * behaviour is required for platform-level endpoints and console commands, and
 * it is why tenant routes must always run behind ResolveTenant.
 *
 * @template TModel of Model
 */
class CompanyScope implements Scope
{
    /**
     * @param  Builder<TModel>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenancy = app(TenancyContext::class);

        if (! $tenancy->has()) {
            return;
        }

        $column = method_exists($model, 'getCompanyColumn')
            ? $model->getCompanyColumn()
            : (string) config('tenancy.column', 'company_id');

        $builder->where($model->qualifyColumn($column), $tenancy->id());
    }
}
