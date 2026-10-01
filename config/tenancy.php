<?php

use App\Models\Concerns\CompanyScope;

return [

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    |
    | The PMS is a multi-tenant SaaS. Phase 1 activates row-level isolation:
    | every tenant-owned table carries a company_id and reads are filtered by
    | a global scope tied to the authenticated membership.
    |
    | Strategies (for possible later use):
    |   - "database" : one shared database, row-level scoping (default)
    |   - "schema"   : one database, one schema per tenant
    |   - "db"       : one database per tenant
    |
    */

    'enabled' => (bool) env('PMS_TENANCY_ENABLED', true),

    'strategy' => env('PMS_TENANCY_STRATEGY', 'database'),

    /*
    | Connection that owns central tables (companies, permissions, roles).
    | These must never be tenant-scoped.
    */
    'central_connection' => env('PMS_TENANCY_CONNECTION_CENTRAL', 'central'),

    /*
    | Model of the tenant registry. The anchor for all tenant-owned records.
    */
    'tenant_model' => 'App\\Models\\Company',

    /*
    | Foreign key added to every tenant-owned table for row-level scoping.
    */
    'column' => 'company_id',

    /*
    | Route/header used to *look up* a tenant. Never a security boundary: access
    | is granted only after an authenticated active membership is proven.
    */
    'header' => 'X-Company-Slug',

    /*
    | Routes reachable without a tenant context (platform-level endpoints).
    */
    'central_routes' => [
        'api/health',
        'api/v1/health',
        'api/v1/auth/login',
        'up',
    ],

    /*
    | Global scope applied by the BelongsToCompany trait.
    */
    'scope' => CompanyScope::class,

];
