<?php

use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\AuditoriaController as AdminAuditoriaController;
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
use App\Http\Controllers\Api\LocalidadController;
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
|
| Autenticación: cookie de sesión de Laravel (HttpOnly) + token CSRF, vía
| Sanctum en modo "stateful" (bootstrap/app.php -> statefulApi()). El
| navegador manda la cookie solo y el JS añade X-CSRF-TOKEN (ver
| core/auth.js). Ya no hay tokens Bearer ni sessionStorage.
|
| Rutas protegidas por 'auth:sanctum' + 'cuenta.activa': requieren sesión
| válida y que la cuenta no esté bloqueada.
| Rutas protegidas además por 'perfil.completo': requieren, encima de la
| sesión, que el usuario ya tenga perfil completo (ver
| Usuario::perfilCompleto()) — son las acciones de "crear algo nuevo"
| que no tiene sentido permitir con el perfil a medias.
|
| Roles (N:N, ver rol/usuario_rol): el rol activo vive en la sesión
| ('rol_activo'). login()/registro() abren la sesión directamente cuando el
| usuario tiene un solo rol; con dos roles dejan el login "pendiente" y el
| usuario lo completa con /seleccionar-rol ('seleccion.rol' exige ese login
| pendiente; en ese momento aún NO está autenticado, por eso la ruta va
| fuera del grupo auth). 'es.admin' exige rol activo admin (y el rol vigente
| en BD) para las rutas /admin/* que son SOLO de administradores. Las que
| comparten admin y moderador (reportes, publicaciones, roomies, mensajes de contacto)
| no llevan 'es.admin': llevan `can:<permiso>`, y el permiso sale del rol
| activo (App\Support\Permisos). agregar-rol-cliente es autoservicio (sin
| restricción extra): ver AuthController::agregarRolCliente().
|
*/

Route::post('/registro', [AuthController::class, 'registro']);
Route::post('/login', [AuthController::class, 'login']);

// Segundo paso del login con más de un rol. Sin 'auth:sanctum' a propósito
// (ver nota de arriba); el gate es 'seleccion.rol'.
Route::post('/seleccionar-rol', [AuthController::class, 'seleccionarRol'])
    ->middleware('seleccion.rol');
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

// Pública (el index la usa sin sesión). Se registra antes que cualquier ruta
// con {parametro} para que 'populares' no se interprete como un id.
Route::get('/localidades/populares', [LocalidadController::class, 'populares'])
    ->middleware('throttle:60,1');

Route::get('/publicaciones', [PublicacionController::class, 'index']);
Route::get('/publicaciones/destacadas', [PublicacionController::class, 'destacadas']);
Route::get('/publicaciones/{publicacion}', [PublicacionController::class, 'show']);
Route::get('/publicaciones/{publicacion}/horarios-ocupados', [ReservaController::class, 'horariosOcupados']);

Route::get('/roomies', [PersonaRoomieController::class, 'index']);
Route::get('/roomies/{persona}', [PersonaRoomieController::class, 'show']);
Route::get('/roomies/{persona}/horarios-ocupados', [CitaController::class, 'horariosOcupados']);

Route::middleware(['auth:sanctum', 'cuenta.activa'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/usuario', [AuthController::class, 'usuarioActual']);
    Route::post('/onboarding', [OnboardingController::class, 'completar']);

    // ---------- Cambio de rol ----------
    // cambiar-rol: desde una sesión ya abierta, salta entre los roles ya
    // asignados (pide contraseña otra vez si el destino es admin o moderador,
    // ver CambiarRolRequest). agregar-rol-cliente: autoservicio para que un
    // admin sin rol cliente se lo agregue (no es elevación de privilegios,
    // no pide contraseña). (seleccionar-rol está más arriba, fuera de este
    // grupo, porque ocurre antes de estar autenticado.)
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

    // ---------- Moderación (compartido admin + moderador) ----------
    // Sin 'es.admin': cada ruta exige el permiso que le corresponde (Gates
    // en AppServiceProvider, mapa en App\Support\Permisos). Un moderador
    // entra aquí; lo que es solo de admin está en el grupo de abajo y a un
    // moderador le responde 403. Los controladores vuelven a comprobar el
    // permiso (y las Policies, el estado del recurso).
    Route::prefix('admin')->group(function () {
        Route::middleware('can:gestionar-reportes')->group(function () {
            Route::get('/reportes', [AdminReporteController::class, 'index']);
            Route::patch('/reportes/{reporte}/resolver', [AdminReporteController::class, 'resolver']);
            Route::patch('/reportes/{reporte}/descartar', [AdminReporteController::class, 'descartar']);
        });
        Route::patch('/reportes/{reporte}/escalar', [AdminReporteController::class, 'escalar'])
            ->middleware('can:escalar-reportes');
        Route::patch('/reportes/{reporte}/devolver', [AdminReporteController::class, 'devolver'])
            ->middleware('can:gestionar-escalados');

        Route::get('/publicaciones', [AdminPublicacionController::class, 'index'])
            ->middleware('can:ver-publicaciones-moderacion');
        Route::delete('/publicaciones/{publicacion}', [AdminPublicacionController::class, 'destroy'])
            ->middleware('can:bajar-publicaciones');
        // Sin route model binding: una publicación soft-deleted queda
        // excluida del binding implícito, así que el id se recibe "en
        // bruto" y se busca con withTrashed() dentro del controlador.
        Route::patch('/publicaciones/{id}/restaurar', [AdminPublicacionController::class, 'restaurar'])
            ->middleware('can:bajar-publicaciones');

        Route::get('/roomies', [AdminPersonaRoomieController::class, 'index'])
            ->middleware('can:ver-roomies-moderacion');
        Route::delete('/roomies/{persona}', [AdminPersonaRoomieController::class, 'destroy'])
            ->middleware('can:bajar-roomies');
        // Sin route model binding por la misma razón que las publicaciones:
        // un perfil soft-deleted queda excluido del binding implícito.
        Route::patch('/roomies/{id}/restaurar', [AdminPersonaRoomieController::class, 'restaurar'])
            ->middleware('can:bajar-roomies');

        Route::middleware('can:responder-mensajes')->group(function () {
            Route::get('/mensajes-contacto', [AdminMensajeContactoController::class, 'index']);
            Route::patch('/mensajes-contacto/{mensajeContacto}/atender', [AdminMensajeContactoController::class, 'atender']);
            Route::post('/mensajes-contacto/{mensajeContacto}/responder', [AdminMensajeContactoController::class, 'responder']);
        });
    });

    // ---------- Administración (solo admin) ----------
    Route::middleware('es.admin')->prefix('admin')->group(function () {
        Route::get('/resumen', [AdminController::class, 'resumen']);
        Route::get('/auditoria', [AdminAuditoriaController::class, 'index'])
            ->middleware('can:ver-auditoria');

        Route::get('/usuarios', [AdminUsuarioController::class, 'index']);
        Route::post('/administradores', [AdminUsuarioController::class, 'crearAdministrador']);
        Route::patch('/usuarios/{usuario}/bloquear', [AdminUsuarioController::class, 'bloquear']);
        Route::patch('/usuarios/{usuario}/desbloquear', [AdminUsuarioController::class, 'desbloquear']);
        Route::patch('/usuarios/{usuario}/promover-admin', [AdminUsuarioController::class, 'promoverAdmin']);
        Route::patch('/usuarios/{usuario}/revocar-admin', [AdminUsuarioController::class, 'revocarAdmin']);
        Route::patch('/usuarios/{usuario}/promover-moderador', [AdminUsuarioController::class, 'promoverModerador'])
            ->middleware('can:gestionar-roles');
        Route::patch('/usuarios/{usuario}/revocar-moderador', [AdminUsuarioController::class, 'revocarModerador'])
            ->middleware('can:gestionar-roles');

        Route::get('/reportes/grafica', [AdminReporteController::class, 'grafica']);
    });
});
