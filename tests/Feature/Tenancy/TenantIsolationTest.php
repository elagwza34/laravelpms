<?php

namespace Tests\Feature\Tenancy;

use App\Enums\MembershipStatus;
use App\Models\CompanyMembership;
use Tests\InteractsWithTenants;
use Tests\TestCase;

/**
 * PROOF OF TENANT ISOLATION.
 *
 * Every test attempts a cross-tenant access and asserts it is refused. Two
 * companies are used that both own a role with the identical slug ("owner"), so
 * a passing suite proves company scoping rather than a lucky unique name.
 */
class TenantIsolationTest extends TestCase
{
    use InteractsWithTenants;

    public function test_member_can_read_their_own_company(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('acme', 'Acme');
        $user = $this->makeMember($company, $company->roles()->where('slug', 'owner')->first());

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/companies/acme')
            ->assertOk()
            ->assertJsonPath('data.slug', 'acme');
    }

    public function test_member_cannot_read_another_company(): void
    {
        $this->seedPermissions();
        $acme = $this->makeCompany('acme', 'Acme');
        $globex = $this->makeCompany('globex', 'Globex');

        $user = $this->makeMember($acme, $acme->roles()->where('slug', 'owner')->first());

        // The user holds an "Owner" role here too, but a DIFFERENT company's.
        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/companies/globex')
            ->assertNotFound();
    }

    public function test_user_of_company_a_is_refused_company_b_and_vice_versa(): void
    {
        $this->seedPermissions();
        $acme = $this->makeCompany('acme', 'Acme');
        $globex = $this->makeCompany('globex', 'Globex');

        $acmeUser = $this->makeMember($acme, $acme->roles()->where('slug', 'owner')->first());
        $globexUser = $this->makeMember($globex, $globex->roles()->where('slug', 'owner')->first());

        $this->actingAsToken($acmeUser)
            ->getJson('/api/v1/companies/acme')->assertOk();

        $this->actingAsToken($acmeUser)
            ->getJson('/api/v1/companies/globex')->assertNotFound();

        $this->actingAsToken($globexUser)
            ->getJson('/api/v1/companies/globex')->assertOk();

        $this->actingAsToken($globexUser)
            ->getJson('/api/v1/companies/acme')->assertNotFound();
    }

    public function test_non_member_cannot_read_a_company_at_all(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('acme', 'Acme');
        $outsider = $this->makePlainUser();

        $this->withToken($this->tokenFor($outsider))
            ->getJson('/api/v1/companies/acme')
            ->assertNotFound();
    }

    /**
     * IDOR: the caller must not reach a foreign company by supplying its numeric
     * id instead of its slug.
     */
    public function test_supplying_a_foreign_company_id_in_the_query_is_ignored(): void
    {
        $this->seedPermissions();
        $acme = $this->makeCompany('acme', 'Acme');
        $globex = $this->makeCompany('globex', 'Globex');

        $user = $this->makeMember($acme, $acme->roles()->where('slug', 'owner')->first());

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/companies/acme?company_id='.$globex->id.'&tenant_id='.$globex->id)
            ->assertOk()
            ->assertJsonPath('data.id', $acme->id);
    }

    /**
     * A forged X-Company-Slug header must not grant access.
     */
    public function test_forged_company_header_does_not_grant_access(): void
    {
        $this->seedPermissions();
        $acme = $this->makeCompany('acme', 'Acme');
        $globex = $this->makeCompany('globex', 'Globex');

        $user = $this->makeMember($acme, $acme->roles()->where('slug', 'owner')->first());

        $this->withToken($this->tokenFor($user))
            ->withHeader('X-Company-Slug', 'globex')
            ->getJson('/api/v1/companies')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'acme');
    }

    public function test_suspended_membership_cannot_access_the_company(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('acme', 'Acme');
        $user = $this->makeMember($company, $company->roles()->where('slug', 'owner')->first());

        CompanyMembership::query()
            ->where('user_id', $user->id)
            ->update(['status' => MembershipStatus::Suspended->value]);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/companies/acme')
            ->assertNotFound();
    }

    public function test_company_list_only_returns_companies_the_user_belongs_to(): void
    {
        $this->seedPermissions();
        $acme = $this->makeCompany('acme', 'Acme');
        $globex = $this->makeCompany('globex', 'Globex');
        $initech = $this->makeCompany('initech', 'Initech');

        $user = $this->makeMember($acme, $acme->roles()->where('slug', 'owner')->first());
        $user->memberships()->create([
            'company_id' => $initech->id,
            'role_id' => $initech->roles()->where('slug', 'owner')->first()->id,
            'status' => MembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $slugs = collect($this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/companies')->assertOk()->json('data'))->pluck('slug');

        $this->assertTrue($slugs->contains('acme'));
        $this->assertTrue($slugs->contains('initech'));
        $this->assertFalse($slugs->contains('globex'), 'Must not leak a company the user does not belong to.');
    }

    public function test_unauthenticated_request_cannot_resolve_a_tenant(): void
    {
        $this->seedPermissions();
        $this->makeCompany('acme', 'Acme');

        $this->getJson('/api/v1/companies/acme')->assertUnauthorized();
    }

    /**
     * The tenant context must not survive into the next request.
     */
    public function test_tenant_context_does_not_leak_between_requests(): void
    {
        $this->seedPermissions();
        $acme = $this->makeCompany('acme', 'Acme');
        $globex = $this->makeCompany('globex', 'Globex');

        $acmeUser = $this->makeMember($acme, $acme->roles()->where('slug', 'owner')->first());
        $globexUser = $this->makeMember($globex, $globex->roles()->where('slug', 'owner')->first());

        // Establish an Acme context first...
        $this->actingAsToken($acmeUser)
            ->getJson('/api/v1/companies/acme')->assertOk();

        // ...then a Globex user must not inherit it.
        $this->actingAsToken($globexUser)
            ->getJson('/api/v1/companies/globex')
            ->assertOk()
            ->assertJsonPath('data.slug', 'globex');
    }
}
