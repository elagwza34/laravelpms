<?php

namespace App\Support\Tenancy\Contracts;

use App\Support\Tenancy\TenancyContext;
use Illuminate\Http\Request;

/**
 * Resolves the tenant that owns the current request.
 *
 * This contract exists so the rest of the application depends on an
 * abstraction rather than a concrete resolution strategy. The concrete
 * implementation (subdomain, custom header, token claim, ...) is a business
 * decision and is deliberately not made yet.
 *
 * @see TenancyContext
 */
interface TenantResolver
{
    /**
     * Resolve the tenant identifier for the given request.
     *
     * Returning null means the request could not be attributed to a tenant.
     */
    public function resolve(Request $request): ?string;
}
