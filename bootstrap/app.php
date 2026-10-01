<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Shared-hosting / reverse-proxy support. Trust the proxy headers that
         * Hostinger sets so generated URLs and $request->ip() stay correct.
         * The trusted proxies list is empty by default (no proxy in local dev)
         * and must be configured in production.
         */
        $middleware->trustProxies(at: [], headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB);

        /*
         * API stack:
         *  1. CORS must run first so preflight OPTIONS requests never 401.
         *  2. ResolveTenant publishes the active tenant before any auth or
         *     authorization logic runs.
         *  3. ForceJsonResponse guarantees JSON output for every API response.
         *  4. Sanctum handles both SPA cookie auth and bearer tokens.
         */
        $middleware->api(prepend: [
            HandleCors::class,
            ResolveTenant::class,
            ForceJsonResponse::class,
        ]);

        // Sanctum SPA authentication for the separate React frontend.
        $middleware->statefulApi();

        // Shared API rate limiting (60 req/min per user/IP by default).
        $middleware->throttleApi();

        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'force.json' => ForceJsonResponse::class,
        ]);

        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Unified JSON error envelope for the API so the React client always
         * receives a predictable shape:
         *
         *   { "message": "...", "errors": { "field": ["..."] } }
         *
         * Internal details (stack traces, SQL) are never leaked unless APP_DEBUG
         * is enabled, in which case Laravel's debug payload is added.
         */
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Unauthenticated.',
                'error_code' => 'unauthenticated',
            ], 401);
        });

        /*
         * ModelNotFoundException is normalised by the framework into a
         * NotFoundHttpException before rendering, and the original exception is
         * kept as the "previous" throwable. Checking the previous exception is
         * what lets the API distinguish "the endpoint does not exist" from
         * "the endpoint exists but the record does not".
         */
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json([
                    'message' => 'Resource not found.',
                    'error_code' => 'not_found',
                ], 404);
            }

            return response()->json([
                'message' => 'Endpoint not found.',
                'error_code' => 'endpoint_not_found',
            ], 404);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage() ?: 'Request could not be completed.',
                'error_code' => 'http_error',
            ], $e->getStatusCode(), $e->getHeaders());
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            /*
             * Any unhandled API exception is logged with full context and
             * reported back to the client as an opaque 500 so internals stay
             * private.
             */
            report($e);

            return response()->json([
                'message' => 'Server error.',
                'error_code' => 'server_error',
            ], 500);
        });
    })->create();
