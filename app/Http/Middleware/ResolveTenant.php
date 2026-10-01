<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenancyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes the tenant context for the current request.
 *
 * Runs at the very top of the API middleware stack. While tenancy is disabled
 * (the foundation stage) this is a transparent no-op, so the application keeps
 * behaving like a single-tenant API. Once config('tenancy.enabled') is turned
 * on, the bound TenantResolver is consulted and the result is published on the
 * TenancyContext singleton for the rest of the request.
 */
class ResolveTenant
{
    public function __construct(protected TenancyContext $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenancy->forget();

        if ($this->tenancy->enabled()) {
            $tenantId = $this->tenancy->resolver()?->resolve($request);

            $this->tenancy->set($tenantId);
        }

        try {
            /** @var Response $response */
            $response = $next($request);
        } finally {
            // Never let a resolved tenant leak into another request/job.
            $this->tenancy->forget();
        }

        return $response;
    }
}
