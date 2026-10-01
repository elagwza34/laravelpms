<?php

namespace Tests\Feature;

use App\Support\Tenancy\TenancyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Verifies the architectural seam prepared for the future multi-tenant PMS.
 *
 * Tenancy must be fully disabled at the foundation stage: every request behaves
 * as single-tenant and no resolver is invoked. These tests lock that behaviour
 * in so enabling tenancy later is a deliberate, reviewed change.
 */
class TenancyArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenancy_is_disabled_by_default(): void
    {
        $this->assertFalse(config('tenancy.enabled'));
    }

    public function test_tenancy_context_returns_null_while_disabled(): void
    {
        $context = app(TenancyContext::class);

        $context->set('acme');

        $this->assertNull($context->id(), 'Tenant id must stay null while tenancy is disabled.');
        $this->assertFalse($context->has());
    }

    public function test_tenancy_context_is_resolvable_from_container(): void
    {
        $this->assertInstanceOf(TenancyContext::class, app(TenancyContext::class));
        $this->assertSame(app(TenancyContext::class), app('tenancy'), 'Alias must resolve.');
    }

    public function test_tenancy_context_forget_clears_the_tenant(): void
    {
        config()->set('tenancy.enabled', true);

        $context = app(TenancyContext::class);
        $context->set('acme');
        $this->assertSame('acme', $context->id());

        $context->forget();
        $this->assertNull($context->id());
    }

    public function test_middleware_does_not_leak_tenant_between_requests(): void
    {
        Route::middleware('api')->get('/api/v1/tenant-probe', function (TenancyContext $tenancy) {
            return response()->json(['tenant' => $tenancy->id()]);
        });

        // Prime the context, then confirm the middleware resets it.
        app(TenancyContext::class)->set('leaked-tenant');

        $response = $this->getJson('/api/v1/tenant-probe');

        $response->assertOk()->assertJsonPath('tenant', null);
    }

    public function test_health_routes_are_listed_as_central_routes(): void
    {
        $this->assertContains('api/v1/health', config('tenancy.central_routes'));
    }
}
