<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies that every failure mode under /api is rendered as a predictable JSON
 * envelope instead of an HTML error page, and that internals are never leaked
 * to the client.
 */
class ApiErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_api_endpoint_returns_json_404(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response->assertNotFound()
            ->assertJsonPath('error_code', 'endpoint_not_found')
            ->assertJsonStructure(['message', 'error_code']);
    }

    public function test_unknown_api_endpoint_never_returns_html(): void
    {
        $response = $this->get('/api/v1/does-not-exist');

        $response->assertNotFound();
        $this->assertStringNotContainsString('<html', (string) $response->getContent());
    }

    public function test_protected_route_returns_json_401(): void
    {
        Route::middleware(['api', 'auth:sanctum'])->get('/api/v1/secret-probe', function () {
            return response()->json(['never' => 'reached']);
        });

        $response = $this->getJson('/api/v1/secret-probe');

        $response->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthenticated');
    }

    public function test_protected_route_accepts_a_valid_bearer_token(): void
    {
        $user = User::factory()->create();

        Route::middleware(['api', 'auth:sanctum'])->get('/api/v1/secret-probe', function () {
            return response()->json(['never' => 'reached']);
        });

        $response = $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/secret-probe');

        $response->assertOk()->assertJsonPath('never', 'reached');
    }

    public function test_missing_model_returns_json_404(): void
    {
        Route::middleware('api')->get('/api/v1/missing-model', function () {
            throw new ModelNotFoundException('No query results for model.');
        });

        $response = $this->getJson('/api/v1/missing-model');

        $response->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_unhandled_exception_returns_opaque_json_500(): void
    {
        Route::middleware('api')->get('/api/v1/boom', function () {
            throw new RuntimeException('super secret internal detail');
        });

        $response = $this->getJson('/api/v1/boom');

        $response->assertStatus(500)->assertJsonPath('error_code', 'server_error');

        // The internal message must never reach the client.
        $this->assertStringNotContainsString('super secret internal detail', (string) $response->getContent());
    }

    public function test_forbidden_response_returns_json_403(): void
    {
        Route::middleware('api')->get('/api/v1/forbidden-probe', function () {
            abort(403, 'This action is unauthorized.');
        });

        $response = $this->getJson('/api/v1/forbidden-probe');

        $response->assertForbidden()
            ->assertJsonPath('error_code', 'http_error');
    }
}
