<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/login');

        // Paksa seluruh route /api/* selalu mengembalikan JSON (bukan HTML redirect)
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        // Alias proteksi API: ->middleware('api.token') atau ->middleware('api.token:usersprofile:read')
        $middleware->alias([
            'api.token' => \App\Http\Middleware\EnsureValidApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                $firstError = $e->validator->errors()->first();
                return response()->json([
                    'success' => false,
                    'message' => $firstError ?: 'Validasi data gagal: periksa kembali input Anda.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });
    })->create();
