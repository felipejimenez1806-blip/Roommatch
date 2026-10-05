<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacion', function (Blueprint $table) {
            $table->increments('id_notificacion');
            $table->unsignedInteger('id_usuario')->comment('Destinatario');
            $table->enum('tipo', [
                'reserva_nueva',
                'reserva_aceptada',
                'reserva_rechazada',
                'cita_nueva',
                'cita_aceptada',
                'cita_rechazada',
                'reporte_nuevo',
                'reporte_resuelto',
                'reporte_descartado',
                'mensaje_contacto_nuevo',
            ]);
            $table->string('mensaje', 500);
            $table->string('objetivo_titulo', 255)->nullable();
            $table->string('link', 255)->nullable()
                ->comment('Ej: mis-publicaciones.html#request-123 -> ruta Laravel equivalente');
            $table->boolean('leida')->default(false);
            $table->dateTime('fecha')->useCurrent();

            $table->foreign('id_usuario', 'fk_notif_usuario')
                ->references('id_usuario')->on('usuario')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->index('id_usuario', 'idx_notif_usuario');
            $table->index('leida', 'idx_notif_leida');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacion');
    }
};
