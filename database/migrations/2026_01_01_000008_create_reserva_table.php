<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserva', function (Blueprint $table) {
            $table->increments('id_reserva');
            $table->unsignedInteger('id_usuario')->comment('Buscador que solicita');
            $table->unsignedInteger('id_publicacion');
            $table->dateTime('fecha_solicitud')->useCurrent();
            $table->date('fecha_visita');
            $table->time('hora_visita')->nullable();
            $table->enum('duracion_estimada', ['1-3', '3-6', '6-12', '12+'])->nullable()
                ->comment('Meses, ver ETIQUETAS_DURACION en reserva.js');
            $table->enum('estado_reserva', ['pendiente', 'aceptada', 'rechazada', 'cancelada'])->default('pendiente');
            $table->string('contacto_nombre', 150)->nullable();
            $table->string('contacto_correo', 150)->nullable();
            $table->string('contacto_telefono', 20)->nullable();
            $table->dateTime('fecha_respuesta')->nullable();

            $table->foreign('id_usuario', 'fk_res_usuario')
                ->references('id_usuario')->on('usuario')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_publicacion', 'fk_res_publicacion')
                ->references('id_publicacion')->on('publicacion')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->index('id_usuario', 'idx_res_usuario');
            $table->index('id_publicacion', 'idx_res_publicacion');
            $table->index('estado_reserva', 'idx_res_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva');
    }
};
