<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorito', function (Blueprint $table) {
            $table->increments('id_favorito');
            $table->unsignedInteger('id_usuario');
            $table->enum('tipo', ['publicacion', 'persona']);
            $table->unsignedInteger('id_publicacion')->nullable();
            $table->unsignedInteger('id_persona')->nullable();
            $table->dateTime('fecha_agregado')->useCurrent();

            $table->unique(['id_usuario', 'id_publicacion'], 'uq_favorito_pub');
            $table->unique(['id_usuario', 'id_persona'], 'uq_favorito_persona');

            $table->foreign('id_usuario', 'fk_fav_usuario')
                ->references('id_usuario')->on('usuario')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_publicacion', 'fk_fav_publicacion')
                ->references('id_publicacion')->on('publicacion')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_persona', 'fk_fav_persona')
                ->references('id_persona')->on('persona_roomie')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->index('id_publicacion', 'idx_fav_publicacion');
            $table->index('id_persona', 'idx_fav_persona');
        });

        // NOTA: la regla "exactamente un objetivo según tipo" YA NO es un CHECK.
        // MySQL 8 no permite CHECK sobre columnas con FK CASCADE (error 3823).
        // Se valida en FavoritoController (tipo='publicacion' => solo
        // id_publicacion; tipo='persona' => solo id_persona).
    }

    public function down(): void
    {
        Schema::dropIfExists('favorito');
    }
};
