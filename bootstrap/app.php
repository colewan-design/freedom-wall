<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'student' => \App\Http\Middleware\EnsureUserIsStudent::class,
        ]);

        // Students and moderators share the one `web` session, so the `guest`
        // middleware has to send each of them somewhere that actually exists.
        // Laravel's default target is `/dashboard`, which this app only serves
        // under the `admin` prefix — without this, a signed-in visitor who
        // opens a login page gets a 404 instead of their own home page.
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->role === 'admin'
            ? route('admin.dashboard')
            : route('wall'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
