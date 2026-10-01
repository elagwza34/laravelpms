<?php

namespace App\Providers;

use App\Support\Tenancy\Contracts\TenantResolver;
use App\Support\Tenancy\Resolvers\MembershipTenantResolver;
use App\Support\Tenancy\TenancyContext;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the multi-tenancy seam into the container.
 *
 * TenancyContext is registered as a SCOPED singleton, which gives exactly one
 * instance per request or per queued job and resets it in between. That is what
 * prevents a tenant resolved for one job from leaking into the next.
 */
class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MembershipTenantResolver::class);

        $this->app->scoped(TenancyContext::class, function ($app): TenancyContext {
            return new TenancyContext(
                $app->bound(TenantResolver::class)
                    ? $app->make(TenantResolver::class)
                    : $app->make(MembershipTenantResolver::class)
            );
        });

        $this->app->alias(TenancyContext::class, 'tenancy');
    }
}
