<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\Resolvers\MembershipTenantResolver;
use App\Support\Tenancy\TenancyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Establishes the tenant context for the current request.
 *
 * ORDERING
 * --------
 * This middleware MUST run after `auth:sanctum`. It is applied per route group
 * (see routes/api.php) rather than in the global API stack, because it needs an
 * authenticated user in order to verify a membership. Running it earlier would
 * see a null user and could never authorise anything.
 *
 * Behaviour:
 *  - No slug supplied          -> no context (platform-level endpoints).
 *  - Valid slug + membership   -> context created; the CompanyScope global
 *                                 scope starts filtering every query.
 *  - Company the caller does
 *    not belong to            -> 404, before the controller is reached.
 *
 * A 404 is used for both "no such company" and "not your company" so slug
 * enumeration is not possible.
 */
class ResolveTenant
{
    public function __construct(
        protected TenancyContext $tenancy,
        protected MembershipTenantResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $this->slugFrom($request);

        if ($slug === null) {
            return $next($request);
        }

        $user = $request->user();

        /*
         * A slug was supplied without authentication. Either the route is
         * misconfigured or the caller is a guest; in both cases no tenant may
         * be established, so the request is rejected rather than silently
         * downgraded to a platform request.
         */
        if ($user === null) {
            throw new NotFoundHttpException('Company not found.');
        }

        $this->tenancy->forget();

        try {
            $resolved = $this->resolver->resolveForUser($user, $slug);

            if ($resolved === null) {
                throw new NotFoundHttpException('Company not found.');
            }

            $this->tenancy->set($resolved->company, $resolved->membership);

            /** @var Response $response */
            $response = $next($request);
        } finally {
            // A resolved tenant must never leak into the next request or job.
            $this->tenancy->forget();
        }

        return $response;
    }

    /**
     * The lookup hint, taken from the route or the configured header.
     */
    private function slugFrom(Request $request): ?string
    {
        $slug = $request->route('company');

        if (! is_string($slug)) {
            $slug = $request->header((string) config('tenancy.header', 'X-Company-Slug'));
        }

        return is_string($slug) && $slug !== '' ? $slug : null;
    }
}
