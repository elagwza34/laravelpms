<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Health / readiness endpoint for the API layer.
 *
 * Used by load balancers, uptime monitors and the React frontend bootstrap to
 * confirm the backend is reachable, the cache is usable and the database
 * answers. It intentionally exposes no sensitive configuration values.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => [
                'status' => 'ok',
                'name' => config('app.name'),
                'env' => config('app.env'),
                'debug' => (bool) config('app.debug'),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
            ],
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'tenancy' => [
                'enabled' => (bool) config('tenancy.enabled'),
                'strategy' => config('tenancy.strategy'),
            ],
        ];

        $healthy = ! in_array('failed', array_column($checks, 'status'), true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    /**
     * @return array{status: string, connection: string, driver: string, version: ?string, error: ?string}
     */
    private function checkDatabase(): array
    {
        try {
            $connectionName = config('database.default');
            $connection = DB::connection();
            $version = $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);

            return [
                'status' => 'ok',
                'connection' => $connectionName,
                'driver' => $connection->getDriverName(),
                'version' => $version !== false ? (string) $version : null,
                'error' => null,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'status' => 'failed',
                'connection' => (string) config('database.default'),
                'driver' => null,
                'version' => null,
                'error' => 'Database connection unavailable.',
            ];
        }
    }

    /**
     * @return array{status: string, store: string, error: ?string}
     */
    private function checkCache(): array
    {
        try {
            $key = 'pms:health-check';
            cache()->put($key, 'ok', 10);
            $value = cache()->pull($key);

            return [
                'status' => $value === 'ok' ? 'ok' : 'failed',
                'store' => (string) config('cache.default'),
                'error' => $value === 'ok' ? null : 'Cache store is not writable.',
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'status' => 'failed',
                'store' => (string) config('cache.default'),
                'error' => 'Cache store unavailable.',
            ];
        }
    }
}
