<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivote usuario <-> rol. PK compuesta (id_usuario, id_rol): un usuario no
 * puede tener el mismo rol duplicado, pero sí puede tener cliente y admin
 * a la vez (ej. un cliente promovido a administrador).
 *
 * asignado_por queda NULL para el rol "cliente" por defecto en el registro
 * público y para las asignaciones de seeders/sistema (ej. la cuenta
 * sembrada "Administrador RoomMatch"); se llena con el id_usuario del
 * admin que promueve a otro usuario desde el panel (Fase 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_rol', function (Blueprint $table) {
            $table->unsignedInteger('id_usuario');
            $table->unsignedTinyInteger('id_rol');
            $table->dateTime('fecha_asignacion')->useCurrent();
            $table->unsignedInteger('asignado_por')->nullable()
                ->comment('id_usuario del admin que asignó este rol. NULL = asignación de sistema (registro por defecto o seeder).');

            $table->primary(['id_usuario', 'id_rol']);

            $table->foreign('id_usuario', 'fk_usuariorol_usuario')
                ->references('id_usuario')->on('usuario')
                ->onDelete('cascade');

            $table->foreign('id_rol', 'fk_usuariorol_rol')
                ->references('id_rol')->on('rol')
                ->onDelete('cascade');

            $table->foreign('asignado_por', 'fk_usuariorol_asignado_por')
                ->references('id_usuario')->on('usuario')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_rol');
    }
};
