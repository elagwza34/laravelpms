<?php

namespace Database\Seeders;

use App\Enums\CompanyStatus;
use App\Enums\MembershipStatus;
use App\Enums\RoleScope;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DEMO / DEVELOPMENT DATA ONLY — DO NOT RUN IN PRODUCTION.
 *
 * Creates two companies with deliberately overlapping role names, so tests and
 * manual QA can verify that a user of one company cannot reach the other.
 *
 * All demo passwords are the same well-known value and every account is
 * clearly prefixed with "demo." so it is obvious what this data is.
 */
class DemoUserSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $demo = Company::query()->firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo Company', 'status' => CompanyStatus::Active->value]
        );

        // A second company exists purely to prove isolation.
        $other = Company::query()->firstOrCreate(
            ['slug' => 'other'],
            ['name' => 'Other Company', 'status' => CompanyStatus::Active->value]
        );

        $this->seedPlatformSuperAdmin();
        $this->seedTenantUser($demo, 'demo', 'owner', 'Demo Owner', 'active');
        $this->seedTenantUser($demo, 'demo', 'manager', 'Demo Manager', 'active');
        $this->seedTenantUser($other, 'other', 'owner', 'Other Owner', 'active');
    }

    /**
     * Platform staff: no company membership, granted through a platform role.
     */
    private function seedPlatformSuperAdmin(): void
    {
        $user = $this->user('demo.platform.admin@example.com', 'Demo Platform Admin', platform: true);

        $role = Role::query()
            ->where('slug', 'super-admin')
            ->where('scope', RoleScope::Platform->value)
            ->first();

        if ($role !== null) {
            $user->platformRoles()->syncWithoutDetaching([$role->id]);
        }
    }

    /**
     * Tenant employee: linked through company_memberships with a tenant role.
     */
    private function seedTenantUser(Company $company, string $companySlug, string $roleSlug, string $name, string $status): void
    {
        $user = $this->user("demo.{$roleSlug}@{$companySlug}.example.com", $name);

        $role = Role::query()
            ->where('slug', $roleSlug)
            ->where('scope', RoleScope::Tenant->value)
            ->where('company_id', $company->id)
            ->first();

        if ($role === null) {
            return;
        }

        CompanyMembership::query()->updateOrCreate(
            ['user_id' => $user->id, 'company_id' => $company->id],
            [
                'role_id' => $role->id,
                'status' => MembershipStatus::Active->value,
                'joined_at' => now(),
            ]
        );
    }

    private function user(string $email, string $name, bool $platform = false): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'is_platform_user' => $platform,
                'email_verified_at' => now(),
            ]
        );
    }
}
