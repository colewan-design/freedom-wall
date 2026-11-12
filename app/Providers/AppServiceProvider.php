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

        RateLimiter::for('submission-vote', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip().'|'.$request->session()->getId());
        });

        RateLimiter::for('chat-message', function (Request $request) {
            return Limit::perMinute(8)->by($request->ip().'|'.$request->session()->getId());
        });

        RateLimiter::for('chat-nickname', function (Request $request) {
            return Limit::perMinutes(5, 5)->by($request->ip().'|'.$request->session()->getId());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinutes(10, 3)->by($request->ip());
        });

        RateLimiter::for('direct-message', function (Request $request) {
            return Limit::perMinute(20)->by('dm|'.$request->user()->id);
        });

        // The forum is public and unmoderated up front, so the write paths are
        // held tighter than the read ones. Starting a thread is the loudest
        // action on the site; replying is a conversation and gets more room.
        RateLimiter::for('thread', function (Request $request) {
            return Limit::perMinutes(10, 3)->by($request->ip());
        });

        RateLimiter::for('thread-reply', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip().'|'.$request->session()->getId());
        });

        RateLimiter::for('thread-vote', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip().'|'.$request->session()->getId());
        });

        RateLimiter::for('thread-report', function (Request $request) {
            return Limit::perMinutes(10, 6)->by($request->ip());
        });

        // JobRadar. Sharing a link lands in a moderated queue, so it is held
        // about as tight as a wall submission; monitors re-scan the board on
        // every save, which is the expensive part.
        RateLimiter::for('job-share', function (Request $request) {
            return Limit::perMinutes(10, 5)->by($request->ip());
        });

        RateLimiter::for('job-report', function (Request $request) {
            return Limit::perMinutes(10, 6)->by($request->ip());
        });

        RateLimiter::for('job-monitor', function (Request $request) {
            return Limit::perMinute(10)->by('job-monitor|'.$request->user()?->id);
        });
    }
}
