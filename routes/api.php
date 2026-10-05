<?php

use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\MensajeContactoController as AdminMensajeContactoController;
use App\Http\Controllers\Api\Admin\PersonaRoomieController as AdminPersonaRoomieController;
use App\Http\Controllers\Api\Admin\PublicacionController as AdminPublicacionController;
use App\Http\Controllers\Api\Admin\ReporteController as AdminReporteController;
use App\Http\Controllers\Api\Admin\UsuarioController as AdminUsuarioController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CitaController;
use App\Http\Controllers\Api\ContactoController;
use App\Http\Controllers\Api\FavoritoController;
use App\Http\Controllers\Api\NotificacionController;
use App\Http\Controllers\Api\PersonaRoomieController;
use App\Http\Controllers\Api\PublicacionController;
use App\Http\Controllers\Api\ReservaController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rutas públicas: registro, login, listar publicaciones/roomies
| (cualquiera puede navegar el catálogo, con o sin sesión).
| Rutas protegidas por 'auth:sanctum': requieren token válido, que el
| frontend manda en el header Authorization: Bearer {token}.
| Rutas protegidas además por 'perfil.completo': requieren, encima del
| token válido, que el usuario ya tenga perfil completo (ver
| Usuario::perfilCompleto()) — son las acciones de "crear algo nuevo"
| que no tiene sentido permitir con el perfil a medias.
|
| Roles (N:N, ver rol/usuario_rol): login()/registro() emiten un token
| real con ability rol:cliente o rol:admin cuando el usuario tiene un
| solo rol, o un token limitado con ability seleccionar-rol cuando tiene
| los dos. 'seleccion.rol' exige esa ability para /seleccionar-rol;
| 'es.admin' exige la ability rol:admin (y el rol vigente en BD) para
| /admin/*. agregar-rol-cliente es autoservicio (sin restricción extra):
| ver AuthController::agregarRolCliente().
|
*/

Route::post('/registro', [AuthController::class, 'registro']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/enviar-codigo', [PasswordResetController::class, 'enviarCodigo'])
    ->middleware('throttle:5,1');
Route::post('/password/verificar-codigo', [PasswordResetController::class, 'verificarCodigo'])
    ->middleware('throttle:10,1');
Route::post('/password/restablecer', [PasswordResetController::class, 'restablecer'])
    ->middleware('throttle:5,1');

// Pública: cualquier visitante puede escribir, con o sin cuenta.
// Limitada a 5 intentos por minuto por IP para evitar spam.
Route::post('/contacto', [ContactoController::class, 'store'])
    ->middleware('throttle:5,1');

Route::get('/publicaciones', [PublicacionController::class, 'index']);
Route::get('/publicaciones/destacadas', [PublicacionController::class, 'destacadas']);
Route::get('/publicaciones/{publicacion}', [PublicacionController::class, 'show']);
Route::get('/publicaciones/{publicacion}/horarios-ocupados', [ReservaController::class, 'horariosOcupados']);

Route::get('/roomies', [PersonaRoomieController::class, 'index']);
Route::get('/roomies/{persona}', [PersonaRoomieController::class, 'show']);
Route::get('/roomies/{persona}/horarios-ocupados', [CitaController::class, 'horariosOcupados']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/usuario', [AuthController::class, 'usuarioActual']);
    Route::post('/onboarding', [OnboardingController::class, 'completar']);

    // ---------- Selección / cambio de rol ----------
    // seleccionar-rol: canjea el token limitado (ability seleccionar-rol,
    // emitido por login/registro cuando hay más de un rol) por el token
    // real de sesión. cambiar-rol: desde una sesión ya abierta, salta
    // entre los roles ya asignados (pide contraseña otra vez solo si el
    // destino es admin, ver CambiarRolRequest). agregar-rol-cliente:
    // autoservicio para que un admin sin rol cliente se lo agregue (no
    // es elevación de privilegios, no pide contraseña).
    Route::post('/seleccionar-rol', [AuthController::class, 'seleccionarRol'])
        ->middleware('seleccion.rol');
    Route::post('/cambiar-rol', [AuthController::class, 'cambiarRol']);
    Route::post('/agregar-rol-cliente', [AuthController::class, 'agregarRolCliente']);

    // ---------- Perfil ----------
    Route::put('/usuario', [UsuarioController::class, 'update']);
    Route::post('/usuario/foto', [UsuarioController::class, 'actualizarFoto']);
    Route::put('/usuario/convivencia', [UsuarioController::class, 'actualizarConvivencia']);

    // ---------- Favoritos ----------
    Route::get('/mis-favoritos', [FavoritoController::class, 'index']);
    Route::get('/mis-favoritos/habitaciones', [FavoritoController::class, 'misHabitaciones']);
    Route::get('/mis-favoritos/roomies', [FavoritoController::class, 'misPersonas']);
    Route::post('/publicaciones/{idPublicacion}/favorito', [FavoritoController::class, 'toggle']);
    Route::post('/roomies/{idPersona}/favorito', [FavoritoController::class, 'togglePersona']);

    // ---------- Mis publicaciones ----------
    Route::get('/mis-publicaciones', [PublicacionController::class, 'misPublicaciones']);
    Route::get('/mis-publicaciones/{publicacion}', [PublicacionController::class, 'misPublicacionShow']);
    Route::put('/mis-publicaciones/{publicacion}', [PublicacionController::class, 'update']);
    Route::patch('/mis-publicaciones/{publicacion}/estado', [PublicacionController::class, 'actualizarEstado']);
    Route::delete('/mis-publicaciones/{publicacion}', [PublicacionController::class, 'destroy']);

    // ---------- Mi perfil de roomie ----------
    // 1 a 1 con el usuario: sin {id} en la URL, siempre es "el mío".
    Route::get('/mi-perfil-roomie', [PersonaRoomieController::class, 'miPerfil']);
    Route::put('/mi-perfil-roomie', [PersonaRoomieController::class, 'update']);
    Route::patch('/mi-perfil-roomie/estado', [PersonaRoomieController::class, 'actualizarEstado']);
    Route::delete('/mi-perfil-roomie', [PersonaRoomieController::class, 'destroy']);

    // ---------- Reservas ----------
    Route::get('/reservas/{reserva}', [ReservaController::class, 'show']);
    Route::get('/mis-reservas', [ReservaController::class, 'misReservas']);
    Route::patch('/reservas/{reserva}/estado', [ReservaController::class, 'actualizarEstado']);
    Route::patch('/reservas/{reserva}/cancelar', [ReservaController::class, 'cancelar']);
    Route::post('/reservas/{reserva}/calificar', [ReservaController::class, 'calificar']);
    Route::post('/reservas/{reserva}/reportar', [ReservaController::class, 'reportar']);

    // ---------- Citas con roomie ----------
    Route::get('/citas/{cita}', [CitaController::class, 'show']);
    Route::get('/mis-citas', [CitaController::class, 'misCitas']);
    Route::get('/mis-citas-recibidas', [CitaController::class, 'citasRecibidas']);
    Route::patch('/citas/{cita}/estado', [CitaController::class, 'actualizarEstado']);
    Route::patch('/citas/{cita}/cancelar', [CitaController::class, 'cancelar']);
    Route::post('/citas/{cita}/calificar', [CitaController::class, 'calificar']);
    Route::post('/citas/{cita}/reportar', [CitaController::class, 'reportar']);

    // ---------- Notificaciones ----------
    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::post('/notificaciones/marcar-leidas', [NotificacionController::class, 'marcarLeidas']);

    // ---------- Acciones que exigen perfil completo ----------
    // Crear/publicar algo nuevo no tiene sentido con el perfil a medias
    // (telefono vacío) — ver Usuario::perfilCompleto().
    Route::middleware('perfil.completo')->group(function () {
        Route::post('/mis-publicaciones', [PublicacionController::class, 'store']);
        Route::post('/mi-perfil-roomie', [PersonaRoomieController::class, 'store']);
        Route::post('/publicaciones/{publicacion}/reservas', [ReservaController::class, 'store']);
        Route::post('/roomies/{persona}/citas', [CitaController::class, 'store']);
    });

    // ---------- Administración ----------
    Route::middleware('es.admin')->prefix('admin')->group(function () {
        Route::get('/resumen', [AdminController::class, 'resumen']);

        Route::get('/usuarios', [AdminUsuarioController::class, 'index']);
        Route::post('/administradores', [AdminUsuarioController::class, 'crearAdministrador']);
        Route::patch('/usuarios/{usuario}/bloquear', [AdminUsuarioController::class, 'bloquear']);
        Route::patch('/usuarios/{usuario}/desbloquear', [AdminUsuarioController::class, 'desbloquear']);
        Route::patch('/usuarios/{usuario}/promover-admin', [AdminUsuarioController::class, 'promoverAdmin']);
        Route::patch('/usuarios/{usuario}/revocar-admin', [AdminUsuarioController::class, 'revocarAdmin']);

        Route::get('/publicaciones', [AdminPublicacionController::class, 'index']);
        Route::delete('/publicaciones/{publicacion}', [AdminPublicacionController::class, 'destroy']);
        // Sin route model binding: una publicación soft-deleted queda
        // excluida del binding implícito, así que el id se recibe "en
        // bruto" y se busca con withTrashed() dentro del controlador.
        Route::patch('/publicaciones/{id}/restaurar', [AdminPublicacionController::class, 'restaurar']);

        Route::get('/roomies', [AdminPersonaRoomieController::class, 'index']);
        Route::delete('/roomies/{persona}', [AdminPersonaRoomieController::class, 'destroy']);
        Route::patch('/roomies/{id}/restaurar', [AdminPersonaRoomieController::class, 'restaurar']);

        Route::get('/reportes', [AdminReporteController::class, 'index']);
        Route::get('/reportes/grafica', [AdminReporteController::class, 'grafica']);
        Route::patch('/reportes/{reporte}/resolver', [AdminReporteController::class, 'resolver']);
        Route::patch('/reportes/{reporte}/descartar', [AdminReporteController::class, 'descartar']);

        Route::get('/mensajes-contacto', [AdminMensajeContactoController::class, 'index']);
        Route::patch('/mensajes-contacto/{mensajeContacto}/atender', [AdminMensajeContactoController::class, 'atender']);
    });
});
