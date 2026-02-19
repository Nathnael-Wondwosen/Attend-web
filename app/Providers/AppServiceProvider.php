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
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('scan', function (Request $request) {
            // Throttle per student per session and keep broad guards.
            $sessionId = (string) ($request->route('session') ?? $request->input('session_id') ?? 'unknown');
            $studentId = (string) ($request->input('student_id') ?? 'unknown');
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(60)->by("scan:{$sessionId}:student:{$studentId}"),
                Limit::perMinute(3000)->by("scan:{$sessionId}"),
                Limit::perMinute(600)->by("scan:ip:{$ip}"),
            ];
        });

        RateLimiter::for('take', function (Request $request) {
            $ip = (string) $request->ip();
            return Limit::perMinute(240)->by("take:ip:{$ip}");
        });

        RateLimiter::for('mobile', function (Request $request) {
            $u = $request->user();
            $key = $u ? ('mobile:user:'.$u->getAuthIdentifier()) : ('mobile:ip:'.$request->ip());
            return Limit::perMinute(240)->by($key);
        });
    }
}
