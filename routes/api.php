<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — /api
|--------------------------------------------------------------------------
|
| These routes are loaded by bootstrap/app.php with the "api" prefix, so the
| group below lives at /api. This file only adds versioning on top.
|
| Versioning policy: breaking changes go into /api/v2 rather than mutating v1.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');
});
