<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces every response produced under /api to be JSON.
 *
 * The React frontend consumes this API exclusively, so HTML error pages are of
 * no use to it. Returning a consistent JSON envelope keeps client-side error
 * handling predictable, including for framework level errors that would
 * otherwise render a Blade view.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        /** @var Response $response */
        $response = $next($request);

        // Guarantee the content type even if a downstream handler changed it.
        if (! $response->headers->has('Content-Type')) {
            $response->headers->set('Content-Type', 'application/json');
        }

        return $response;
    }
}
