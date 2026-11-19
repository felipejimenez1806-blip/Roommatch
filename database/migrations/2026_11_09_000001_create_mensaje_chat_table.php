<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensajes del chat entre dos usuarios que tienen una reserva o una cita entre ellos.
 *
 * Cada fila es un mensaje de un usuario (emisor) para otro (receptor).
 * El chat lo atiende el servicio Java (procedimientos sp_chat_*), que es quien
 * valida que los dos usuarios tengan una reserva o cita pendiente/aceptada.
 *
 * estado: 'enviado' | 'no_leido' | 'leido' | 'eliminado'
 *   - enviado:   recién guardado, la otra persona todavía no ve que le llegó (valor por defecto)
 *   - no_leido:  la otra persona ya lo vio en su lista de chats pero no ha abierto la conversación
 *   - leido:     la otra persona abrió la conversación
 *   - eliminado: el emisor lo eliminó (se conserva en la base pero ya no se muestra)
 *
 * editado: 1 si el emisor cambió el texto después de enviarlo. Es aparte del estado
 * porque editar un mensaje no cambia si fue leído o no.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensaje_chat', function (Blueprint $table) {
            $table->increments('id_mensaje');
            $table->unsignedInteger('id_emisor')->comment('Quien escribe el mensaje');
            $table->unsignedInteger('id_receptor')->comment('Quien lo recibe');
            $table->string('mensaje', 500);
            $table->string('estado', 10)->default('enviado')->comment('enviado, no_leido, leido o eliminado');
            $table->boolean('editado')->default(false);
            $table->dateTime('fecha_envio')->useCurrent();

            $table->foreign('id_emisor', 'fk_chat_emisor')
                ->references('id_usuario')->on('usuario')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->foreign('id_receptor', 'fk_chat_receptor')
                ->references('id_usuario')->on('usuario')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->index(['id_emisor', 'id_receptor'], 'idx_chat_emisor_receptor');
            $table->index('id_receptor', 'idx_chat_receptor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensaje_chat');
    }
};
