<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cita', function (Blueprint $table) {
            $table->increments('id_cita');
            $table->unsignedInteger('id_usuario')->comment('Quien solicita la cita');
            $table->unsignedInteger('id_persona')->comment('Perfil de roomie con quien se agenda');
            $table->dateTime('fecha_solicitud')->useCurrent();
            $table->date('fecha_cita');
            $table->time('hora_cita');
            $table->enum('tipo_encuentro', ['sitio', 'publico', 'otro']);
            $table->string('lugar_encuentro', 255);
            $table->text('mensaje')->nullable();
            $table->enum('estado_cita', ['pendiente', 'aceptada', 'rechazada', 'cancelada'])->default('pendiente');
            $table->string('contacto_nombre', 150)->nullable();
            $table->string('contacto_correo', 150)->nullable();
            $table->string('contacto_telefono', 20)->nullable();
            $table->dateTime('fecha_respuesta')->nullable();

            $table->foreign('id_usuario', 'fk_cita_usuario')
                ->references('id_usuario')->on('usuario')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_persona', 'fk_cita_persona')
                ->references('id_persona')->on('persona_roomie')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->index('id_usuario', 'idx_cita_usuario');
            $table->index('id_persona', 'idx_cita_persona');
            $table->index('estado_cita', 'idx_cita_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cita');
    }
};
