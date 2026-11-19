<?php

use App\Services\AgendaService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Mi agenda: reenvío de avisos al servicio de Java
|--------------------------------------------------------------------------
| Sin opciones reenvía solo lo que quedó pendiente (si Java estaba caído).
| Con --todo reenvía todas las reservas y citas aceptadas o canceladas recientes
| (sirve para cargar lo que ya estaba aceptado). Java no duplica: se puede correr
| las veces que haga falta.
*/
Artisan::command(
    'agenda:sincronizar {--todo : Reenvía todas las reservas y citas recientes, no solo las pendientes}',
    function (AgendaService $agenda) {
        $agenda->cargaInicialSiHaceFalta();

        if ($this->option('todo')) {
            $r = $agenda->sincronizarTodo();
            $this->info("Enviados: {$r['enviados']} · fallidos: {$r['fallidos']}".($r['interrumpido'] ? ' · el servicio de agenda no responde.' : ''));

            return;
        }

        $r = $agenda->sincronizarPendientes();
        $this->info("Enviados: {$r['enviados']} · fallidos: {$r['fallidos']} · pendientes: {$r['pendientes']}");
    }
)->purpose('Reenvía a la agenda (Java) lo que no se pudo avisar');

// Se ejecutan solos mientras el planificador de Laravel esté activo (php artisan schedule:work,
// o la línea de cron en el servidor). Si no lo está, los pendientes se reenvían igual en el
// siguiente aceptar/cancelar.
Schedule::command('agenda:sincronizar')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('agenda:sincronizar --todo')->hourly()->withoutOverlapping();
