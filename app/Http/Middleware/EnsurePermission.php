<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Requires one or more permissions on the current route.
 *
 * Usage: ->middleware('permission:products.create')
 *
 * The check reads the user's effective permissions, which are derived from the
 * membership role inside a tenant context and from the platform role outside it.
 * The two namespaces never mix, so a tenant role can never satisfy a platform
 * permission.
 */
class EnsurePermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AccessDeniedHttpException('Unauthenticated.');
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        throw new AccessDeniedHttpException(
            'You do not have permission to perform this action.'
        );
    }
}
