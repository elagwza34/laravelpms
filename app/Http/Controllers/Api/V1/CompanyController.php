<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Tenancy\Resolvers\MembershipTenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Companies the caller is allowed to see.
 *
 * The index lists only companies the caller actually belongs to (or all of them
 * for platform staff). There is no "show any company by id" endpoint on
 * purpose: reading a company must go through the tenant middleware so that
 * membership is proven, not assumed.
 */
class CompanyController extends Controller
{
    public function __construct(protected MembershipTenantResolver $companies) {}

    /**
     * Companies the authenticated user may switch into.
     */
    public function index(Request $request): JsonResponse
    {
        $companies = $this->companies->companiesFor($request->user());

        return response()->json([
            'data' => $companies->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'status' => $company->status->value,
                'pms_enabled' => $company->status->allowsPmsAccess(),
            ])->all(),
        ]);
    }

    /**
     * A single company.
     *
     * The {company} route parameter is matched by SLUG and resolved by the
     * `tenant` middleware, which has already verified an active membership.
     * The extra membership check below is intentional defence in depth: if the
     * middleware is ever dropped from a route, this still refuses the read.
     */
    public function show(Request $request, string $company): JsonResponse
    {
        $model = Company::query()->where('slug', $company)->first();

        if ($model === null) {
            abort(404, 'Company not found.');
        }

        $user = $request->user();

        if (! $user->isPlatformUser() && ! $user->belongsToCompany($model->id)) {
            // 404 rather than 403 so slugs cannot be enumerated.
            abort(404, 'Company not found.');
        }

        return response()->json([
            'data' => [
                'id' => $model->id,
                'name' => $model->name,
                'slug' => $model->slug,
                'status' => $model->status->value,
                'pms_enabled' => $model->status->allowsPmsAccess(),
                'my_role' => $user->memberships()
                    ->where('company_id', $model->id)
                    ->with('role')
                    ->first()?->role?->name,
            ],
        ]);
    }
}
