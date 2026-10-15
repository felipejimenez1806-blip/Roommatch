<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialAuthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Estas rutas sirven el shell HTML (Blade). Los datos reales se cargan
| después vía fetch() a /api/*, ver api.php.
|
| Autenticación: cookie de sesión de Laravel (HttpOnly) + CSRF. El servidor
| ve al usuario en cada página, así que el control de acceso es middleware
| real (ya no hay scripts de guardia en el navegador):
|
|   auth          visitante sin sesión -> /login
|   cuenta.activa cuenta bloqueada o rol activo inválido -> sesión cerrada
|   zona.cliente  páginas del sitio de cliente (layouts.app): un admin que
|                 navega como admin va a /admin; un usuario con el perfil
|                 incompleto va a /onboarding
|   zona.admin    páginas de administración: solo con el rol activo admin
|
| Esto es experiencia de uso; la protección de los DATOS sigue siendo la de
| la API ('auth:sanctum', 'es.admin', 'perfil.completo').
|
*/

// ==========================================================================
// Rutas públicas sin navbar de cliente (no pasan por zona.cliente)
// ==========================================================================

Route::get('/login', fn () => view('auth.login'))->name('login');
Route::get('/signup', fn () => view('auth.signup'))->name('signup');
Route::get('/recuperar-password', fn () => view('auth.recuperar-password'))->name('password.recuperar');
Route::get('/verificar-codigo', fn () => view('auth.verificar-codigo'))->name('password.verificar');
Route::get('/nueva-contrasena', fn () => view('auth.nueva-contrasena'))->name('password.nueva');
Route::get('/terminos-condiciones', fn () => view('terminos-condiciones'))->name('terminos');
Route::get('/error413', fn () => view('errors.413'))->name('error413');

Route::get('/auth/{proveedor}/redirect', [SocialAuthController::class, 'redirect'])
    ->name('auth.social.redirect');
Route::get('/auth/{proveedor}/callback', [SocialAuthController::class, 'callback'])
    ->name('auth.social.callback');

// Onboarding: exige sesión pero NO pasa por zona.cliente (esa zona manda aquí
// a quien tiene el perfil incompleto; pasar por ella sería un bucle).
Route::get('/onboarding', fn () => view('onboarding'))
    ->middleware(['auth', 'cuenta.activa'])
    ->name('onboarding');

// ==========================================================================
// Sitio de cliente — públicas (se pueden ver con o sin sesión)
// ==========================================================================

Route::middleware(['cuenta.activa', 'zona.cliente'])->group(function () {
    Route::get('/', fn () => view('index'))->name('index');

    Route::get('/ayuda', fn () => view('ayuda'))->name('ayuda');
    Route::get('/faq', fn () => view('faq'))->name('faq');
    Route::get('/siguenos', fn () => view('siguenos'))->name('siguenos');
    Route::get('/nosotros', fn () => view('nosotros'))->name('nosotros');
    Route::get('/contactanos', fn () => view('contactanos'))->name('contactanos');

    Route::get('/habitaciones', fn () => view('habitaciones'))->name('habitaciones');
    Route::get('/publicacion/{id}', fn (string $id) => view('publicacion', ['id' => $id]))->name('publicacion');
    Route::get('/roomies', fn () => view('roomies'))->name('roomies');
    Route::get('/roomie/{id}', fn (string $id) => view('perfil-roomie', ['id' => $id]))->name('roomie');
});

// ==========================================================================
// Sitio de cliente — privadas (exigen sesión)
// ==========================================================================

Route::middleware(['auth', 'cuenta.activa', 'zona.cliente'])->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
    Route::get('/perfil', fn () => view('perfil'))->name('perfil');

    Route::get('/crear-publicacion', fn () => view('crear-publicacion'))->name('crear-publicacion');
    Route::get('/mis-publicaciones', fn () => view('mis-publicaciones'))->name('mis-publicaciones');
    Route::get('/reserva/{id}', fn (string $id) => view('reserva', ['id' => $id]))->name('reserva');
    Route::get('/confirmacion', fn () => view('confirmacion'))->name('confirmacion');

    Route::get('/crear-perfil-roomie', fn () => view('crear-perfil-roomie'))->name('crear-perfil-roomie');
    Route::get('/cita/{id}', fn (string $id) => view('cita-roomie', ['id' => $id]))->name('cita');
    Route::get('/confirmacion-cita', fn () => view('confirmacion-cita'))->name('confirmacion-cita');
});

// ==========================================================================
// Administración — usan layouts.admin / layouts.admin-bare (sin nav de
// cliente). Nombres de ruta sin cambios ('admin' y
// 'admin.crear-administrador') para no romper los route() de las vistas.
// ==========================================================================

Route::prefix('admin')->middleware(['auth', 'cuenta.activa', 'zona.admin'])->group(function () {
    Route::get('/', fn () => view('admin'))->name('admin');

    // Cuando el panel se divida en secciones (usuarios, publicaciones,
    // reportes, mensajes-contacto), cada una entra aquí como su propia ruta,
    // por ejemplo:
    // Route::get('/usuarios', fn () => view('admin.usuarios'))->name('admin.usuarios');

    Route::get('/nuevo-administrador', fn () => view('crear-administrador'))
        ->name('admin.crear-administrador');
});
