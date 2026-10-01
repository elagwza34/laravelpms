<?php

namespace App\Providers;

use App\Support\Tenancy\Contracts\TenantResolver;
use App\Support\Tenancy\TenancyContext;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the multi-tenancy seam into the container.
 *
 * The TenancyContext is registered as a singleton so the tenant resolved by
 * ResolveTenant middleware is visible to every later part of the request.
 *
 * No concrete TenantResolver is bound on purpose: how a tenant is identified
 * (subdomain, header, token claim) is a business decision that has not been
 * made yet. Binding one later is a one-line change in register() — every other
 * layer already depends only on the contract.
 */
class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenancyContext::class, function ($app): TenancyContext {
            // scoped(): one instance per request/job, reset by Laravel between
            // requests, which prevents tenant leakage in queued workers.
            return new TenancyContext(
                $app->bound(TenantResolver::class) ? $app->make(TenantResolver::class) : null
            );
        });

        $this->app->alias(TenancyContext::class, 'tenancy');
    }
}
