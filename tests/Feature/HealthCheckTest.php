<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the health endpoint, the most important smoke test of the whole
 * foundation: it exercises the HTTP stack, the MySQL/SQLite connection and the
 * cache store in one request.
 */
class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok_payload(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => [
                    'app' => ['status', 'name', 'env', 'debug', 'laravel', 'php', 'timezone', 'locale'],
                    'database' => ['status', 'connection', 'driver', 'version', 'error'],
                    'cache' => ['status', 'store', 'error'],
                    'tenancy' => ['enabled', 'strategy'],
                ],
            ]);

        $this->assertSame('ok', $response->json('checks.app.status'));
        $this->assertSame('ok', $response->json('checks.database.status'));
        $this->assertSame('ok', $response->json('checks.cache.status'));
    }

    public function test_health_endpoint_reports_ok_when_every_check_passes(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()->assertJsonPath('status', 'ok');
    }

    public function test_health_endpoint_returns_json_content_type(): void
    {
        $response = $this->get('/api/v1/health');

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }

    public function test_health_endpoint_does_not_leak_sensitive_configuration(): void
    {
        $response = $this->getJson('/api/v1/health');

        $body = $response->getContent();

        $this->assertIsString($body);
        $this->assertStringNotContainsString('DB_PASSWORD', $body);
        $this->assertStringNotContainsString('APP_KEY', $body);
        $this->assertStringNotContainsString(config('app.key'), $body);
    }

    public function test_health_endpoint_reports_tenancy_disabled_by_default(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('checks.tenancy.enabled', false)
            ->assertJsonPath('checks.tenancy.strategy', 'database');
    }
}
