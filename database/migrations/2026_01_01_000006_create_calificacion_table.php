<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calificacion', function (Blueprint $table) {
            $table->increments('id_calificacion');
            $table->unsignedInteger('id_usuario')->nullable()->comment('Autor de la reseña (NULL solo para mock data)');
            $table->enum('tipo', ['publicacion', 'persona']);
            $table->unsignedInteger('id_publicacion')->nullable();
            $table->unsignedInteger('id_persona')->nullable();
            $table->string('autor_nombre', 150)->comment('Snapshot del nombre mostrado');
            $table->tinyInteger('puntuacion');
            $table->text('comentario')->nullable();
            $table->dateTime('fecha_calificacion')->useCurrent();

            $table->foreign('id_usuario', 'fk_calif_usuario')
                ->references('id_usuario')->on('usuario')
                ->onDelete('set null')->onUpdate('cascade');
            $table->foreign('id_publicacion', 'fk_calif_publicacion')
                ->references('id_publicacion')->on('publicacion')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_persona', 'fk_calif_persona')
                ->references('id_persona')->on('persona_roomie')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->index('id_usuario', 'idx_calif_usuario');
            $table->index('id_publicacion', 'idx_calif_publicacion');
            $table->index('id_persona', 'idx_calif_persona');
        });

        DB::statement("ALTER TABLE calificacion ADD CONSTRAINT chk_calif_puntuacion CHECK (puntuacion BETWEEN 1 AND 10)");
        DB::statement("
            ALTER TABLE calificacion ADD CONSTRAINT chk_calif_objetivo CHECK (
                (tipo = 'publicacion' AND id_publicacion IS NOT NULL AND id_persona IS NULL) OR
                (tipo = 'persona' AND id_persona IS NOT NULL AND id_publicacion IS NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('calificacion');
    }
};
