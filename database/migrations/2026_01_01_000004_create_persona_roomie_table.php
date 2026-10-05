<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persona_roomie', function (Blueprint $table) {
            $table->increments('id_persona');
            $table->unsignedInteger('id_usuario')->comment('Dueño del perfil roomie');
            $table->integer('edad');
            $table->enum('genero', ['Mujer', 'Hombre', 'Otro']);
            $table->enum('ocupacion', ['Estudiante', 'Profesional', 'Freelance', 'Otro']);
            $table->string('zona', 100);
            $table->string('ciudad', 100)->default('Bogotá');
            $table->decimal('presupuesto', 10, 2);
            $table->string('telefono', 20)->nullable();
            $table->string('img', 500)->nullable();
            $table->text('descripcion')->nullable();
            $table->enum('estado_busqueda', ['buscando', 'ya_encontro', 'pausado'])->default('buscando');
            $table->dateTime('fecha_publicacion')->useCurrent();
            $table->dateTime('fecha_actualizacion')->nullable();
            $table->softDeletes()->comment('Borrado lógico: si tiene fecha, el perfil se considera eliminado para el usuario pero el registro sigue en BD');

            // NOTA: ya NO hay unique(id_usuario) a nivel de BD. Con soft
            // delete, un perfil "eliminado" seguiría ocupando ese id_usuario
            // de forma única y le impediría al usuario crear uno nuevo. La
            // regla de negocio "un perfil ACTIVO por usuario" se valida en
            // PersonaRoomieController antes de crear (buscando uno con
            // deleted_at IS NULL para ese id_usuario).
            $table->foreign('id_usuario', 'fk_persona_usuario')
                ->references('id_usuario')->on('usuario')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->index('id_usuario', 'idx_persona_usuario');
            $table->index('zona', 'idx_persona_zona');
            $table->index('estado_busqueda', 'idx_persona_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persona_roomie');
    }
};
