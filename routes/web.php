<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialAuthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Estas rutas solo sirven el shell HTML (Blade). Los datos reales se
| cargan después vía fetch() a /api/*, mandando el token en el header
| Authorization — ahí es donde vive la protección real por rol
| (middleware 'es.admin' sobre /api/admin/*, ver api.php).
|
| IMPORTANTE: esta app no usa autenticación por sesión/cookie para las
| rutas web — el login es puramente por token, guardado en
| sessionStorage por el JS del cliente. Una petición GET normal de
| navegación (como estas) NO manda ese token, así que un middleware
| tipo `auth` / `es.admin` de Laravel puesto aquí no vería nada y
| dejaría pasar a cualquiera. Por eso la separación de acceso en este
| archivo se hace en dos capas:
|
|   1. Agrupación por prefijo /admin, para que la estructura de rutas
|      refleje la separación real y podamos aplicar el guard de abajo
|      solo a este grupo.
|   2. resources/js/admin-guard.js, cargado de forma bloqueante en
|      <head> ANTES de pintar el <body> (en layouts.admin y
|      layouts.app respectivamente): revisa el rol guardado en
|      sessionStorage y redirige de inmediato si no corresponde a esa
|      zona. No reemplaza la protección real de datos (esa ya la
|      tienes con 'es.admin' en la API) — solo evita que alguien vea
|      el HTML/JS de la zona equivocada o un flash de contenido ajeno.
|
| Migrar esto a un middleware real de Laravel implicaría pasar el login
| a Sanctum SPA (cookies httpOnly + CSRF) en vez de token manual en
| sessionStorage — es un cambio más grande, queda como mejora futura.
|
*/

// ==========================================================================
// Rutas públicas (sin sesión)
// ==========================================================================

Route::get('/', fn () => view('index'))->name('index');
Route::get('/login', fn () => view('auth.login'))->name('login');
Route::get('/signup', fn () => view('auth.signup'))->name('signup');
Route::get('/recuperar-password', fn () => view('auth.recuperar-password'))->name('password.recuperar');
Route::get('/verificar-codigo', fn () => view('auth.verificar-codigo'))->name('password.verificar');
Route::get('/nueva-contrasena', fn () => view('auth.nueva-contrasena'))->name('password.nueva');
Route::get('/onboarding', fn () => view('onboarding'))->name('onboarding');

Route::get('/ayuda', fn () => view('ayuda'))->name('ayuda');
Route::get('/faq', fn () => view('faq'))->name('faq');
Route::get('/terminos-condiciones', fn () => view('terminos-condiciones'))->name('terminos');
Route::get('/siguenos', fn () => view('siguenos'))->name('siguenos');
Route::get('/nosotros', fn () => view('nosotros'))->name('nosotros');
Route::get('/contactanos', fn () => view('contactanos'))->name('contactanos');
Route::get('/error413', fn () => view('errors.413'))->name('error413');

Route::get('/auth/{proveedor}/redirect', [SocialAuthController::class, 'redirect'])
    ->name('auth.social.redirect');
Route::get('/auth/{proveedor}/callback', [SocialAuthController::class, 'callback'])
    ->name('auth.social.callback');

// ==========================================================================
// Rutas de cliente (roomie / arrendador) — usan layouts.app.
// admin-guard.js redirige a /admin si el usuario logueado es admin.
// ==========================================================================

Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
Route::get('/perfil', fn () => view('perfil'))->name('perfil');

Route::get('/habitaciones', fn () => view('habitaciones'))->name('habitaciones');
Route::get('/publicacion/{id}', fn (string $id) => view('publicacion', ['id' => $id]))->name('publicacion');
Route::get('/crear-publicacion', fn () => view('crear-publicacion'))->name('crear-publicacion');
Route::get('/mis-publicaciones', fn () => view('mis-publicaciones'))->name('mis-publicaciones');
Route::get('/reserva/{id}', fn (string $id) => view('reserva', ['id' => $id]))->name('reserva');
Route::get('/confirmacion', fn () => view('confirmacion'))->name('confirmacion');

Route::get('/roomies', fn () => view('roomies'))->name('roomies');
Route::get('/roomie/{id}', fn (string $id) => view('perfil-roomie', ['id' => $id]))->name('roomie');
Route::get('/crear-perfil-roomie', fn () => view('crear-perfil-roomie'))->name('crear-perfil-roomie');
Route::get('/cita/{id}', fn (string $id) => view('cita-roomie', ['id' => $id]))->name('cita');
Route::get('/confirmacion-cita', fn () => view('confirmacion-cita'))->name('confirmacion-cita');

// ==========================================================================
// Rutas de administración — usan layouts.admin (sin nav de cliente).
// admin-guard.js redirige a /dashboard si el usuario logueado NO es admin.
//
// Nombres de ruta sin cambios ('admin' y 'admin.crear-administrador')
// para no romper los route()/@route() que ya existan en las vistas.
// ==========================================================================

Route::prefix('admin')->group(function () {
    Route::get('/', fn () => view('admin'))->name('admin');

    // Próximo paso: cuando dividamos el panel en secciones (usuarios,
    // publicaciones, reportes, mensajes-contacto), cada una entra aquí
    // como su propia ruta dentro de este mismo grupo, por ejemplo:
    // Route::get('/usuarios', fn () => view('admin.usuarios'))->name('admin.usuarios');

    Route::get('/nuevo-administrador', fn () => view('crear-administrador'))
        ->name('admin.crear-administrador');
});
