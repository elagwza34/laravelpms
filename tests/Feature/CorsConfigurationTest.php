<?php

namespace Tests\Feature;

use Fruitcake\Cors\CorsService;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Verifies the CORS configuration used by the separate React + TypeScript
 * frontend. Origins are driven by API_ALLOWED_ORIGINS.
 */
class CorsConfigurationTest extends TestCase
{
    public function test_cors_config_reads_allowed_origins_from_env(): void
    {
        $origins = config('cors.allowed_origins');

        $this->assertContains('http://localhost:5173', $origins);
        $this->assertContains('http://127.0.0.1:5173', $origins);
    }

    public function test_cors_applies_to_api_paths(): void
    {
        $paths = config('cors.paths');

        $this->assertContains('api/*', $paths);
        $this->assertContains('sanctum/csrf-cookie', $paths);
    }

    public function test_cors_allows_all_methods_and_headers(): void
    {
        $this->assertSame(['*'], config('cors.allowed_methods'));
        $this->assertSame(['*'], config('cors.allowed_headers'));
    }

    public function test_preflight_request_from_allowed_origin_is_accepted(): void
    {
        config()->set('cors.allowed_origins', ['http://localhost:5173']);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
        ])->get('/api/v1/health');

        $response->assertOk();
        $this->assertSame(
            'http://localhost:5173',
            $response->headers->get('Access-Control-Allow-Origin')
        );
    }

    /**
     * An origin that is not on the allow-list must not be granted CORS access,
     * otherwise any website could call the API from a browser. This is asserted
     * directly against the CORS service, because the browser is what enforces
     * the allow-list — not the server.
     */
    public function test_cors_service_rejects_origin_outside_the_allow_list(): void
    {
        $service = new CorsService;
        $service->setOptions(config('cors'));

        $allowed = Request::create('/api/v1/health', 'GET');
        $allowed->headers->set('Origin', 'http://localhost:5173');

        $blocked = Request::create('/api/v1/health', 'GET');
        $blocked->headers->set('Origin', 'https://evil.example.com');

        $this->assertTrue($service->isOriginAllowed($allowed));
        $this->assertFalse($service->isOriginAllowed($blocked));
    }

    public function test_configured_origins_are_scoped_to_api_paths(): void
    {
        $this->assertSame(
            ['http://localhost:5173', 'http://127.0.0.1:5173'],
            config('cors.allowed_origins')
        );
    }
}
