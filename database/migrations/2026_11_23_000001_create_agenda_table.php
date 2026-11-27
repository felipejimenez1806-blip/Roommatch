<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Agenda: UNA FILA POR PERSONA Y POR EVENTO.
 * Cuando una reserva o una cita se acepta, las dos personas reciben su propia fila.
 * Así cada una sincroniza su fila con su propio Google Calendar
 * (google_event_id guarda el id del evento que Google le asigne a esa persona).
 *
 * La segunda tabla, google_calendar_conexion, guarda el permiso (refresh token) que cada persona le da
 * a RoomMatch al pulsar "Conectar con Google Calendar" en Mi agenda (Java).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda', function (Blueprint $table) {
            $table->increments('id_agenda');
            $table->unsignedInteger('id_usuario')->comment('Dueño de esta fila: cada persona tiene la suya');
            $table->unsignedInteger('id_otro')->nullable()->comment('La otra persona del evento');
            $table->boolean('es_solicitante')->default(false)->comment('1 = esta persona pidió la reserva o la cita');
            $table->enum('tipo', ['reserva', 'cita']);
            $table->unsignedInteger('id_origen')->comment('id_reserva o id_cita, según el tipo');
            $table->string('titulo', 255);
            $table->string('lugar', 255)->nullable();
            $table->date('fecha');
            $table->time('hora')->nullable()->comment('Sin hora = evento de todo el día');
            $table->unsignedSmallInteger('duracion_min')->default(30);
            $table->enum('estado', ['activa', 'cancelada'])->default('activa');

            // Para Google Calendar: id del evento en el calendario de esta persona y cuándo se sincronizó
            $table->string('google_event_id', 255)->nullable();
            $table->dateTime('google_sincronizado_en')->nullable();

            $table->timestamp('creado')->useCurrent();
            $table->timestamp('actualizado')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('id_usuario', 'fk_agenda_usuario')
                ->references('id_usuario')->on('usuario')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_otro', 'fk_agenda_otro')
                ->references('id_usuario')->on('usuario')
                ->onDelete('set null')->onUpdate('cascade');

            // Una persona no puede tener dos veces el mismo evento
            $table->unique(['id_usuario', 'tipo', 'id_origen'], 'uq_agenda_evento');
            $table->index(['id_usuario', 'fecha'], 'idx_agenda_usuario_fecha');
        });

        // Quién conectó su Google Calendar. Una fila por persona; si desconecta, se borra.
        Schema::create('google_calendar_conexion', function (Blueprint $table) {
            $table->unsignedInteger('id_usuario')->primary();
            $table->string('refresh_token', 512)->comment('Permiso de larga duración que Google entrega al conectar');
            $table->timestamp('conectado_en')->useCurrent();

            $table->foreign('id_usuario', 'fk_google_conexion_usuario')
                ->references('id_usuario')->on('usuario')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_conexion');
        Schema::dropIfExists('agenda');
    }
};
