<?php

namespace Database\Seeders;

use App\Enums\MembershipStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Unit;
use App\Support\Tenancy\TenancyContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * DEMO MASTER DATA — development only, never production.
 *
 * Gives a fresh install something to look at without inventing business data
 * the PMS does not have yet. Everything created here is owned by a single demo
 * company and is clearly marked as demo data by its naming.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('slug', 'demo')->first();

        // Nothing to attach to when the demo company has not been seeded yet.
        if ($company === null) {
            return;
        }

        $this->withTenancy($company);

        foreach (['Unit', 'Box', 'Carton', 'Kg', 'Gram', 'Liter', 'Meter'] as $unit) {
            Unit::query()->firstOrCreate(
                ['company_id' => $company->id, 'name' => $unit],
                ['abbreviation' => Str::substr($unit, 0, 3)]
            );
        }

        foreach (['Demo Brand', 'Demo Electronics', 'Demo Apparel'] as $brand) {
            Brand::query()->firstOrCreate(
                ['company_id' => $company->id, 'slug' => Str::slug($brand)],
                ['name' => $brand]
            );
        }

        // A small nested category tree to demonstrate parent/child support.
        $electronics = Category::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'demo-electronics'],
            ['name' => 'Electronics']
        );

        Category::query()->firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'demo-mobile-phones'],
            ['name' => 'Mobile Phones', 'parent_id' => $electronics->id]
        );
    }

    /**
     * Establish the tenant context so the scoped models assign company_id.
     */
    private function withTenancy(Company $company): void
    {
        $membership = new CompanyMembership;
        $membership->forceFill([
            'company_id' => $company->id,
            'status' => MembershipStatus::Active,
        ]);

        app(TenancyContext::class)->set($company, $membership);
    }
}
