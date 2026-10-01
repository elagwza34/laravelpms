<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenancyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\PaymentRequiredHttpException;

/**
 * Blocks the Product Management System when a subscription is not active.
 *
 * A company whose subscription expired or was suspended keeps every record and
 * can still sign in and reach the customer portal; only PMS endpoints are
 * refused. Renewal restores access immediately because nothing is deleted.
 */
class EnsureSubscriptionIsActive
{
    public function __construct(protected TenancyContext $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tenancy->has() && ! $this->tenancy->allowsPmsAccess()) {
            throw new PaymentRequiredHttpException(
                'Your subscription is not active. The Product Management System is unavailable until it is renewed.'
            );
        }

        return $next($request);
    }
}
