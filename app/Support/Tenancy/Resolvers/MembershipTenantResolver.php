<?php

namespace App\Support\Tenancy\Resolvers;

use App\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\Contracts\TenantResolver;
use App\Support\Tenancy\ResolvedTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Derives the active tenant from the caller's authenticated membership.
 *
 * SECURITY MODEL
 * --------------
 * The tenant is decided in two steps:
 *
 *   1. A slug is read from the request (the `company` route parameter or the
 *      X-Company-Slug header). This is only a *lookup hint*.
 *   2. The company is loaded together with the caller's ACTIVE membership.
 *
 * Step 2 is the real boundary. When the authenticated user holds no active
 * membership for the requested company, resolve() returns null and the request
 * is rejected. Supplying a different slug, guessing an id, or adding a
 * `company_id` to the payload cannot bypass this, because none of those inputs
 * are ever used as the decision.
 *
 * Platform staff may enter a company without holding a membership, but only
 * while they carry an active account; their permissions still come from their
 * platform role, never from the tenant.
 */
class MembershipTenantResolver implements TenantResolver
{
    /**
     * The raw slug supplied by the caller. Never trusted on its own.
     */
    public function resolve(Request $request): ?string
    {
        $user = $request->user();

        // An unauthenticated caller can never establish a tenant context.
        if (! $user instanceof User) {
            return null;
        }

        return $this->resolveForUser($user, $this->slugFrom($request))?->company->slug;
    }

    /**
     * Resolve the tenant for a user and slug, with the full outcome.
     */
    public function resolveForUser(?User $user, ?string $slug): ?ResolvedTenant
    {
        if ($slug === null || $slug === '' || $user === null) {
            return null;
        }

        $company = Company::query()->where('slug', $slug)->first();

        if ($company === null) {
            return null;
        }

        if ($user->is_platform_user && $user->isActive()) {
            return ResolvedTenant::forPlatform($company);
        }

        $membership = $user->memberships()
            ->where('company_id', $company->getKey())
            ->where('status', MembershipStatus::Active)
            ->with('role.permissions')
            ->first();

        return $membership === null
            ? null
            : ResolvedTenant::forMembership($company, $membership);
    }

    /**
     * Every company the user may switch into right now.
     *
     * @return Collection<int, Company>
     */
    public function companiesFor(User $user): Collection
    {
        if ($user->is_platform_user) {
            return Company::query()->orderBy('name')->get();
        }

        return Company::query()
            ->whereIn('id', $user->memberships()
                ->where('status', MembershipStatus::Active)
                ->select('company_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Read the lookup hint from the request.
     */
    private function slugFrom(Request $request): ?string
    {
        $slug = $request->route('company');

        if (! is_string($slug)) {
            $slug = $request->header('X-Company-Slug');
        }

        return is_string($slug) && $slug !== '' ? $slug : null;
    }
}
