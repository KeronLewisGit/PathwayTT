<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Livewire binds #[Url] props before mount(); an array-valued query
        // parameter (?q[]=x) would otherwise fatally hit a string property.
        $middleware->web(prepend: [
            App\Http\Middleware\RejectArrayQueryStrings::class,
        ]);

        $middleware->alias([
            'verified.optional' => App\Http\Middleware\VerifyEmailIfRequired::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
