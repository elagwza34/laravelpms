<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Support\Tenancy\Contracts\TenantResolver;
use Illuminate\Support\Traits\Macroable;

/**
 * Holds the tenant that is active for the current request / process.
 *
 * This is the single source of truth for "which company is this request acting
 * on behalf of". It is bound as a scoped singleton and is always cleared once
 * the response has been produced, so a tenant can never leak into the next
 * request or the next queued job.
 *
 * The tenant is only ever populated from a membership the authenticated user
 * actually holds — see MembershipTenantResolver. A slug arriving in the URL is
 * used to *look up* a company, never to grant access to it.
 */
class TenancyContext
{
    use Macroable;

    protected ?Company $company = null;

    protected ?CompanyMembership $membership = null;

    public function __construct(protected ?TenantResolver $resolver = null) {}

    /**
     * Activate the tenant together with the membership that authorised it.
     */
    public function set(?Company $company, ?CompanyMembership $membership = null): void
    {
        $this->company = $company;
        $this->membership = $membership;
    }

    /**
     * The active company, or null when no tenant is active.
     */
    public function company(): ?Company
    {
        return $this->company;
    }

    /**
     * The membership that authorised this tenant context.
     */
    public function membership(): ?CompanyMembership
    {
        return $this->membership;
    }

    /**
     * The active tenant identifier.
     */
    public function id(): ?int
    {
        return $this->company?->getKey();
    }

    public function has(): bool
    {
        return $this->company !== null;
    }

    /**
     * Reset the context. Called before and after every request so long-lived
     * workers never leak a tenant between jobs.
     */
    public function forget(): void
    {
        $this->company = null;
        $this->membership = null;
    }

    /**
     * Whether the active tenant may currently use the Product Management
     * System. An expired or suspended company keeps all of its data but loses
     * PMS access until its subscription is restored.
     */
    public function allowsPmsAccess(): bool
    {
        return $this->company?->status?->allowsPmsAccess() ?? false;
    }

    public function resolver(): ?TenantResolver
    {
        return $this->resolver;
    }
}
