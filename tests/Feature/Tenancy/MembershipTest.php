<?php

namespace Tests\Feature\Tenancy;

use App\Enums\MembershipStatus;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Tests\InteractsWithTenants;
use Tests\TestCase;

/**
 * Proves the membership layer behaves as specified: one membership per
 * user+company, enforced by the database rather than by application checks.
 */
class MembershipTest extends TestCase
{
    use InteractsWithTenants;

    public function test_a_user_can_hold_memberships_in_several_companies(): void
    {
        $this->seedPermissions();
        $acme = $this->makeCompany('acme', 'Acme');
        $globex = $this->makeCompany('globex', 'Globex');

        $user = $this->makeMember($acme, $acme->roles()->where('slug', 'owner')->first());

        $user->memberships()->create([
            'company_id' => $globex->id,
            'role_id' => $globex->roles()->where('slug', 'owner')->first()->id,
            'status' => MembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->assertSame(2, $user->memberships()->count());
        $this->assertTrue($user->belongsToCompany($acme->id));
        $this->assertTrue($user->belongsToCompany($globex->id));
    }

    /**
     * The unique index must block a duplicate even if application code is
     * bypassed or raced.
     */
    public function test_duplicate_membership_in_the_same_company_is_rejected_by_the_database(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('acme', 'Acme');
        $user = $this->makeMember($company, $company->roles()->where('slug', 'owner')->first());

        $this->expectException(QueryException::class);

        CompanyMembership::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role_id' => $company->roles()->where('slug', 'owner')->first()->id,
            'status' => MembershipStatus::Active->value,
        ]);
    }

    public function test_membership_records_the_join_date(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('acme', 'Acme');
        $user = $this->makeMember($company, $company->roles()->where('slug', 'owner')->first());

        $membership = CompanyMembership::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($membership->joined_at);
        $this->assertTrue($membership->isActive());
    }

    public function test_invited_membership_is_not_usable_yet(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('acme', 'Acme');
        $user = $this->makeMember($company, $company->roles()->where('slug', 'owner')->first());

        CompanyMembership::query()
            ->where('user_id', $user->id)
            ->update(['status' => MembershipStatus::Invited->value]);

        $this->actingAsToken($user)
            ->getJson('/api/v1/companies/acme')
            ->assertNotFound();
    }

    public function test_user_without_any_membership_sees_an_empty_company_list(): void
    {
        $this->seedPermissions();
        $this->makeCompany('acme', 'Acme');
        $outsider = $this->makePlainUser();

        $this->actingAsToken($outsider)
            ->getJson('/api/v1/companies')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_platform_user_can_see_all_companies_without_membership(): void
    {
        $this->seedPermissions();
        $this->makeCompany('acme', 'Acme');
        $this->makeCompany('globex', 'Globex');

        $admin = $this->makePlatformUser('root@test.com', ['*']);

        $this->actingAsToken($admin)
            ->getJson('/api/v1/companies')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_platform_user_has_no_membership_records(): void
    {
        $this->seedPermissions();
        $admin = $this->makePlatformUser('root@test.com', ['*']);

        // Platform staff belong to the operator, not to any customer.
        $this->assertSame(0, $admin->memberships()->count());
    }

    public function test_deleting_a_company_removes_its_memberships_but_keeps_the_user(): void
    {
        $this->seedPermissions();
        $company = $this->makeCompany('acme', 'Acme');
        $user = $this->makeMember($company, $company->roles()->where('slug', 'owner')->first());

        $company->forceDelete();

        $this->assertDatabaseMissing('company_memberships', ['user_id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_users_table_has_no_company_id_column(): void
    {
        /*
         * The architecture deliberately avoids user_id -> company_id. Membership
         * is a join table so a person can belong to many companies.
         */
        $this->assertFalse(
            Schema::hasColumn('users', 'company_id'),
            'users must not carry a company_id; membership lives in company_memberships.'
        );

        $this->assertTrue(User::query()->getConnection()
            ->getSchemaBuilder()->hasTable('company_memberships'));
    }
}
