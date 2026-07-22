<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        RedirectIfAuthenticated::redirectUsing(fn (Request $request) => $request->user()?->role === 'admin'
            ? route('admin.dashboard')
            : route('feed'));

        Authenticate::redirectUsing(fn (Request $request) => $request->routeIs('admin.*')
            ? route('admin.login')
            : route('login'));

        RateLimiter::for('submission', function (Request $request) {
            return Limit::perMinutes(5, 3)->by($request->ip());
        });

        RateLimiter::for('chat-message', function (Request $request) {
            return Limit::perMinute(8)->by($request->ip().'|'.$request->session()->getId());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinutes(10, 3)->by($request->ip());
        });
    }
}
