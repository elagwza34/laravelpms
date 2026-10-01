<?php

use App\Http\Controllers\Api\V1\AttributeController;
use App\Http\Controllers\Api\V1\AttributeValueController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Products\ProductController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\UnitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — /api
|--------------------------------------------------------------------------
|
| Versioning policy: breaking changes go into /api/v2 rather than mutating v1.
|
| MIDDLEWARE ORDER IS SECURITY-CRITICAL
| ------------------------------------
| Every authenticated group applies, in this order:
|
|   auth:sanctum        -> establishes WHO is calling
|   tenant              -> proves they may act for that company (membership)
|   permission:...      -> proves they may do this thing (role -> permissions)
|   subscription.active -> blocks the PMS when the plan has expired
|
| `tenant` MUST come after `auth`, because it verifies an authenticated
| membership. It is not in the global API stack for exactly that reason.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {

    // --- Public -----------------------------------------------------------
    Route::get('health', HealthController::class)->name('health');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        // Throttled per IP to blunt brute-force attempts.
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:auth')
            ->name('login');
    });

    // --- Authenticated, platform level (no tenant context) ----------------
    Route::middleware(['auth:sanctum'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
    });

    // --- Tenant scoped ----------------------------------------------------
    // {company} is the tenant slug. ResolveTenant turns it into a real tenant
    // context ONLY when the caller holds an active membership for it.
    Route::middleware(['auth:sanctum', 'tenant', 'subscription.active'])->group(function (): void {
        /*
         * {company} is bound to a plain string so controller arguments line up
         * with route order.
         *
         * {product} is deliberately NOT route-model bound. SubstituteBindings
         * runs in the global API stack, before ResolveTenant, so a bound model
         * would resolve with no tenant active and the CompanyScope would apply
         * no filter — letting a foreign product id through. The controller
         * resolves it with Product::query() after all middleware instead.
         */
        Route::bind('company', fn ($value) => (string) $value);

        Route::get('companies/{company}', [CompanyController::class, 'show'])
            ->name('companies.show');

        // --- Products -------------------------------------------------------
        // Permission is declared per verb so products.view can never be traded
        // for products.delete.
        //
        /*
         * Controller argument order follows ROUTE order, not signature order:
         * Laravel passes route parameters positionally, so {company} must be
         * the first parameter of every action below.
         *
         * This is also the honest shape — the tenant slug IS part of the
         * request. It is never treated as authority: ResolveTenant has already
         * proved an active membership for it, and the product is bound through
         * a CompanyScope query.
         */
        Route::prefix('{company}/products')->name('products.')->group(function (): void {
            Route::get('/', [ProductController::class, 'index'])
                ->middleware('permission:products.view')->name('index');
            Route::post('/', [ProductController::class, 'store'])
                ->middleware('permission:products.create')->name('store');

            Route::get('{product}', [ProductController::class, 'show'])
                ->middleware('permission:products.view')->name('show');
            Route::match(['put', 'patch'], '{product}', [ProductController::class, 'update'])
                ->middleware('permission:products.update')->name('update');
            Route::delete('{product}', [ProductController::class, 'destroy'])
                ->middleware('permission:products.delete')->name('destroy');

            Route::post('{product}/activate', [ProductController::class, 'activate'])
                ->middleware('permission:products.update')->name('activate');
            Route::post('{product}/deactivate', [ProductController::class, 'deactivate'])
                ->middleware('permission:products.update')->name('deactivate');
        });

        /*
         * Master data is tenant-owned, so it is nested under {company} exactly
         * like products. Keeping one shape means the tenant is always explicit
         * in the URL and there is no second, header-only resolution path to
         * forget about.
         *
         * Laravel passes route parameters POSITIONALLY, so every route here
         * declares ->parameters([...]) to hand the controller exactly the
         * arguments it expects and nothing more.
         */
        Route::prefix('{company}')->group(function (): void {
            Route::apiResource('brands', BrandController::class)
                ->middleware('permission:brands.view,brands.create,brands.update,brands.delete')
                ->names('brands');

            Route::apiResource('categories', CategoryController::class)
                ->middleware('permission:categories.view,categories.create,categories.update,categories.delete')
                ->names('categories');

            Route::apiResource('units', UnitController::class)
                ->middleware('permission:units.view,units.create,units.update,units.delete')
                ->names('units');

            Route::apiResource('suppliers', SupplierController::class)
                ->middleware('permission:suppliers.view,suppliers.create,suppliers.update,suppliers.delete')
                ->names('suppliers');

            // Attributes carry their values nested, so they use explicit routes
            // rather than apiResource.
            Route::prefix('attributes')->name('attributes.')->group(function (): void {
                Route::get('/', [AttributeController::class, 'index'])
                    ->middleware('permission:attributes.view')->name('index');
                Route::post('/', [AttributeController::class, 'store'])
                    ->middleware('permission:attributes.create')->name('store');
                Route::get('{attribute}', [AttributeController::class, 'show'])
                    ->middleware('permission:attributes.view')->name('show');
                Route::match(['put', 'patch'], '{attribute}', [AttributeController::class, 'update'])
                    ->middleware('permission:attributes.update')->name('update');
                Route::delete('{attribute}', [AttributeController::class, 'destroy'])
                    ->middleware('permission:attributes.delete')->name('destroy');

                Route::get('{attribute}/values', [AttributeValueController::class, 'index'])
                    ->middleware('permission:attributes.view')->name('values.index');
                Route::post('{attribute}/values', [AttributeValueController::class, 'store'])
                    ->middleware('permission:attributes.create')->name('values.store');
                Route::get('{attribute}/values/{attribute_value}', [AttributeValueController::class, 'show'])
                    ->middleware('permission:attributes.view')->name('values.show');
                Route::match(['put', 'patch'], '{attribute}/values/{attribute_value}', [AttributeValueController::class, 'update'])
                    ->middleware('permission:attributes.update')->name('values.update');
                Route::delete('{attribute}/values/{attribute_value}', [AttributeValueController::class, 'destroy'])
                    ->middleware('permission:attributes.delete')->name('values.destroy');
            });
        });
    });
});
