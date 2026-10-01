<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Define the named rate limiters used by the API middleware stack.
     *
     * bootstrap/app.php applies the "api" limiter to every /api request, so the
     * limiter itself has to be declared here. The budget is keyed by user id
     * when the request is authenticated and falls back to the client IP for
     * guests, which prevents one shared office IP from exhausting the budget.
     *
     * These are safe production defaults for shared hosting; adjust the numbers
     * once the real PMS traffic profile is known.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by(
                $request->user()?->getAuthIdentifier() ?: $request->ip()
            );
        });

        /*
         * Stricter limiter for unauthenticated endpoints such as login.
         *
         * The return type is left untyped on purpose: an array of limits lets a
         * caller be throttled both per IP (stops one source brute-forcing many
         * accounts) and per account (stops a botnet from spreading one guess
         * across many IPs).
         *
         * @return Limit|array<int, Limit>
         */
        RateLimiter::for('auth', function (Request $request): Limit|array {
            return [
                Limit::perMinute(5)->by('ip|'.$request->ip()),
                Limit::perMinute(20)->by('email|'.mb_strtolower((string) $request->input('email'))),
            ];
        });
    }
}
