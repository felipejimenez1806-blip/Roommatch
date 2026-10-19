<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de auditoría de moderación y administración.
 *
 * Cada fila es algo que hizo una persona del equipo (moderador o admin):
 * resolver/descartar/escalar un reporte, bajar o restaurar una publicación,
 * responder un mensaje de contacto, bloquear/desbloquear, promover/revocar
 * roles. Es de solo escritura desde la app: nunca se edita ni se borra.
 *
 *  - rol_activo: con qué rol actuó en esa sesión. Importa porque una cuenta
 *    puede tener moderador y admin a la vez y los permisos dependen del
 *    rol activo, no de la suma de ambos.
 *  - entidad_titulo: snapshot legible (título de la publicación, nombre del
 *    usuario...) para que la fila se entienda aunque la entidad ya no exista.
 *  - id_usuario usa 'set null': si la cuenta del actor se elimina, la
 *    acción se conserva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accion_moderacion', function (Blueprint $table) {
            $table->increments('id_accion');
            $table->unsignedInteger('id_usuario')->nullable()->comment('Quien realizó la acción');
            $table->string('rol_activo', 30)->comment('Rol con el que actuó: moderador | admin');
            $table->string('accion', 50)->comment('Ej: reporte_resuelto, publicacion_bajada, mensaje_respondido');
            $table->string('entidad_tipo', 30)->comment('reporte | publicacion | persona | usuario | mensaje_contacto');
            $table->unsignedInteger('entidad_id')->nullable();
            $table->string('entidad_titulo', 255)->nullable()->comment('Snapshot legible de la entidad al momento de la acción');
            $table->json('detalle')->nullable()->comment('Motivo, nota u otros datos de la acción');
            $table->dateTime('fecha')->useCurrent();

            $table->foreign('id_usuario', 'fk_accmod_usuario')
                ->references('id_usuario')->on('usuario')
                ->onDelete('set null')->onUpdate('cascade');

            $table->index('id_usuario', 'idx_accmod_usuario');
            $table->index('accion', 'idx_accmod_accion');
            $table->index(['entidad_tipo', 'entidad_id'], 'idx_accmod_entidad');
            $table->index('fecha', 'idx_accmod_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accion_moderacion');
    }
};
