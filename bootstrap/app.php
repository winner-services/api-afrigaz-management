<?php

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'work.access' => \App\Http\Middleware\CheckWorkAccess::class,
            'login.time' => \App\Http\Middleware\LoginTimeMiddleware::class,
        ]);
        $middleware->redirectGuestsTo(function (Request $request) {

            if ($request->is('api/*')) {
                return null;
            }

            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (AuthenticationException $e, Request $request) {

            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'status'  => 401,
                    'message' => 'Non authentifié. Token manquant ou invalide.',
                ], 401);
            }
        });
    })
    ->create();

// use Illuminate\Auth\AuthenticationException;
// use Illuminate\Foundation\Application;
// use Illuminate\Foundation\Configuration\Exceptions;
// use Illuminate\Foundation\Configuration\Middleware;
// use Illuminate\Support\Facades\Request;

// return Application::configure(basePath: dirname(__DIR__))
//     ->withRouting(
//         web: __DIR__ . '/../routes/web.php',
//         api: __DIR__ . '/../routes/api.php',
//         commands: __DIR__ . '/../routes/console.php',
//         health: '/up',
//     )
//     ->withMiddleware(function ($middleware) {

//         $middleware->alias([
//             'work.access' => \App\Http\Middleware\CheckWorkAccess::class,
//         ]);
//     })
//     // ->withMiddleware(function (Middleware $middleware): void {
//     //     //
//     // })
//     ->withExceptions(function (Exceptions $exceptions): void {

//         $exceptions->render(function (AuthenticationException $e, Request $request) {
//             if ($request->is('api/v1/*')) {
//                 return response()->json([
//                     'success' => false,
//                     'status'  => 401,
//                     'message' => 'Non authentifié. Token absent ou invalide.',
//                 ], 401);
//             }
//         });
//         $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
//             return $request->is('api/v1/*') || $request->expectsJson();
//         });
//     })->create();
