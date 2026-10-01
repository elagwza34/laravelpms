<?php

namespace App\Support\Tenancy\Contracts;

use App\Support\Tenancy\Resolvers\MembershipTenantResolver;
use Illuminate\Http\Request;

/**
 * Resolves the company slug a request is attempting to act on behalf of.
 *
 * Implementations must treat the value they read from the request as an
 * untrusted lookup hint only. Returning a slug merely means "this caller is
 * asking about that company"; the caller is still responsible for proving an
 * authenticated membership before a tenant context is created.
 *
 * @see MembershipTenantResolver
 */
interface TenantResolver
{
    /**
     * The slug the request asked for, or null when it did not ask for one.
     */
    public function resolve(Request $request): ?string;
}
