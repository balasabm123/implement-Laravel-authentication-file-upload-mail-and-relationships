<?php

use App\Http\Middleware\AgeCheck;
use App\Http\Middleware\CountryCheck;
use App\Http\Middleware\langage;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
        /*$middleware->append(AgeCheck::class);
        $middleware->appendToGroup('check1',[
            AgeCheck::class,
            CountryCheck::class,
        ]);*/
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return null; // Return 401 instead of redirecting
            }

            return route('login');
        });
        $middleware->appendToGroup('SetLang', [langage::class,]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. Please login first.',
            ], 401);
        });

    })->create();
