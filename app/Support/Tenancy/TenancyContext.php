<?php

namespace App\Support\Tenancy;

use App\Support\Tenancy\Contracts\TenantResolver;
use Illuminate\Support\Traits\Macroable;

/**
 * Holds the tenant that is active for the current request / process.
 *
 * The PMS will run multi-tenant. This class is the single place that carries
 * the active tenant identifier through the request lifecycle. It is bound as a
 * singleton in the service container, so middleware can populate it early and
 * repositories, policies and jobs can read it later.
 *
 * IMPORTANT: at the foundation stage tenancy is disabled by default
 * (config('tenancy.enabled') === false) and no resolver is bound. When the
 * business rules for tenant resolution are defined, bind a TenantResolver in
 * a service provider and enable the feature flag. Nothing else has to change.
 *
 * @see TenantResolver
 */
class TenancyContext
{
    use Macroable;

    protected ?string $tenantId = null;

    public function __construct(protected ?TenantResolver $resolver = null) {}

    /**
     * Store the active tenant for the remainder of the request.
     */
    public function set(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Alias of set(), kept for readability inside middleware.
     */
    public function identify(?string $tenantId): void
    {
        $this->set($tenantId);
    }

    /**
     * The active tenant identifier, or null when not resolved / disabled.
     */
    public function id(): ?string
    {
        if (! config('tenancy.enabled')) {
            return null;
        }

        return $this->tenantId;
    }

    public function has(): bool
    {
        return $this->id() !== null;
    }

    /**
     * Reset the context. Called at the start of every request so long-lived
     * workers (Octane, queues) never leak a tenant between jobs.
     */
    public function forget(): void
    {
        $this->tenantId = null;
    }

    /**
     * Whether tenancy is currently active for this application.
     */
    public function enabled(): bool
    {
        return (bool) config('tenancy.enabled');
    }

    /**
     * The configured resolution strategy, exposed for diagnostics.
     */
    public function strategy(): string
    {
        return (string) config('tenancy.strategy', 'database');
    }

    public function resolver(): ?TenantResolver
    {
        return $this->resolver;
    }
}
