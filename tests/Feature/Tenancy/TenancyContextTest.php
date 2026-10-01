<?php

namespace Tests\Feature\Tenancy;

use App\Enums\CompanyStatus;
use App\Support\Tenancy\TenancyContext;
use Tests\InteractsWithTenants;
use Tests\TestCase;

/**
 * Covers the TenancyContext container wiring introduced in Phase 1.
 *
 * The behavioural guarantees (isolation, permissions, membership) live in
 * TenantIsolationTest, MembershipTest and PermissionTest. This file only checks
 * that the context object itself is bound and behaves.
 */
class TenancyContextTest extends TestCase
{
    use InteractsWithTenants;

    public function test_tenancy_context_is_resolvable_and_aliased(): void
    {
        $this->assertInstanceOf(TenancyContext::class, app(TenancyContext::class));
        $this->assertSame(app(TenancyContext::class), app('tenancy'));
    }

    public function test_context_is_empty_until_a_tenant_is_set(): void
    {
        $context = app(TenancyContext::class);

        $this->assertFalse($context->has());
        $this->assertNull($context->id());
        $this->assertNull($context->company());
        $this->assertNull($context->membership());
    }

    public function test_setting_a_company_publishes_its_identity(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('acme', 'Acme');

        $context = app(TenancyContext::class);
        $context->set($company);

        $this->assertTrue($context->has());
        $this->assertSame($company->id, $context->id());
        $this->assertSame('acme', $context->company()->slug);
    }

    public function test_forget_clears_both_company_and_membership(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('acme', 'Acme');
        $user = $this->makeMember($company, $company->roles()->where('slug', 'owner')->first());

        $context = app(TenancyContext::class);
        $context->set($company, $user->memberships()->first());

        $this->assertNotNull($context->membership());

        $context->forget();

        $this->assertNull($context->company());
        $this->assertNull($context->membership());
        $this->assertNull($context->id());
    }

    public function test_expired_company_blocks_pms_access_but_keeps_the_tenant(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('acme', 'Acme', CompanyStatus::Expired);

        $context = app(TenancyContext::class);
        $context->set($company);

        $this->assertTrue($context->has(), 'An expired tenant still exists.');
        $this->assertFalse($context->allowsPmsAccess(), 'But the PMS stays locked.');
    }

    public function test_active_and_trial_companies_allow_pms_access(): void
    {
        $this->seedPermissions();

        $active = $this->makeCompany('active-co', 'Active Co', CompanyStatus::Active);
        $trial = $this->makeCompany('trial-co', 'Trial Co', CompanyStatus::Trial);
        $suspended = $this->makeCompany('susp-co', 'Suspended Co', CompanyStatus::Suspended);

        $context = app(TenancyContext::class);

        $context->set($active);
        $this->assertTrue($context->allowsPmsAccess());

        $context->set($trial);
        $this->assertTrue($context->allowsPmsAccess());

        $context->set($suspended);
        $this->assertFalse($context->allowsPmsAccess());
    }

    public function test_tenancy_column_is_company_id(): void
    {
        $this->assertSame('company_id', config('tenancy.column'));
        $this->assertSame('App\\Models\\Company', config('tenancy.tenant_model'));
    }
}
