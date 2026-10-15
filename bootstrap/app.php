<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sesión por cookie (HttpOnly) también en /api/*: cuando la petición
        // viene del propio sitio (Origin/Referer en sanctum.stateful), Sanctum
        // activa cookies, sesión y verificación CSRF. Sin Origin/Referer válido
        // la petición no tiene sesión y las rutas protegidas responden 401.
        $middleware->statefulApi();

        // 'auth' en rutas web: un visitante sin sesión va al login.
        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->alias([
            'es.admin' => \App\Http\Middleware\EsAdmin::class,
            'perfil.completo' => \App\Http\Middleware\EnsurePerfilCompleto::class,
            'seleccion.rol' => \App\Http\Middleware\RequiereSeleccionRol::class,
            'cuenta.activa' => \App\Http\Middleware\VerificarCuentaActiva::class,
            'zona.cliente' => \App\Http\Middleware\ZonaCliente::class,
            'zona.admin' => \App\Http\Middleware\ZonaAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 419 = el token CSRF no coincide: casi siempre es una sesión vencida
        // (o una página que llevaba abierta demasiado tiempo). El JS lo trata
        // como "sesión expirada".
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() === 419 && ($request->is('api/*') || $request->expectsJson())) {
                return response()->json([
                    'mensaje' => 'Tu sesión expiró. Recarga la página e inicia sesión de nuevo.',
                ], 419);
            }
        });
    })->create();
