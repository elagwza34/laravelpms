<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\HealthController;
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
    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::get('companies/{company}', [CompanyController::class, 'show'])
            ->name('companies.show');
    });
});
