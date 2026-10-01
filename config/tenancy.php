<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy (architecture only)
    |--------------------------------------------------------------------------
    |
    | The PMS will be multi-tenant. This configuration file only establishes the
    | architectural seam so tenancy can be implemented later without reworking
    | the codebase. No tenant business logic is executed at this stage.
    |
    | Supported strategies (for later use):
    |   - "database" : one shared database, row-level scoping (default)
    |   - "schema"   : one database, one schema per tenant
    |   - "db"       : one database per tenant
    |
    */

    'enabled' => (bool) env('PMS_TENANCY_ENABLED', false),

    'strategy' => env('PMS_TENANCY_STRATEGY', 'database'),

    /*
    | Connection that owns the central tables (tenants, tenant_user, ...).
    | These tables must never be tenant-scoped.
    */
    'central_connection' => env('PMS_TENANCY_CONNECTION_CENTRAL', 'central'),

    /*
    | Fully qualified model of the central Tenant registry.
    | Declared as a string so the class does not have to exist yet.
    */
    'tenant_model' => 'App\\Models\\Tenant',

    /*
    | Column added to every tenant-owned table for row-level scoping.
    */
    'column' => 'tenant_id',

    /*
    | Guards that may act across tenant boundaries (e.g. central support staff).
    | Empty until the authorization rules are defined.
    */
    'bypass_guards' => [],

    /*
    | Routes that must stay reachable while tenancy is being resolved.
    */
    'central_routes' => [
        'api/health',
        'api/v1/health',
        'sanctum/csrf-cookie',
        'up',
    ],

];
