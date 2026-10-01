<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use App\Models\CompanyMembership;

/**
 * Outcome of resolving a tenant for a request.
 *
 * It records *how* the tenant was granted — via a membership or via platform
 * staff status — because that distinction changes how permissions are
 * evaluated for the rest of the request.
 */
final class ResolvedTenant
{
    private function __construct(
        public readonly Company $company,
        public readonly ?CompanyMembership $membership,
        public readonly bool $viaPlatform,
    ) {}

    /**
     * Tenant granted by an active membership: permissions come from the role.
     */
    public static function forMembership(Company $company, CompanyMembership $membership): self
    {
        return new self($company, $membership, viaPlatform: false);
    }

    /**
     * Tenant entered by platform staff: no membership, permissions come from the
     * platform role instead.
     */
    public static function forPlatform(Company $company): self
    {
        return new self($company, membership: null, viaPlatform: true);
    }
}
