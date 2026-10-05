<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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

        DB::statement("
            ALTER TABLE favorito ADD CONSTRAINT chk_fav_objetivo CHECK (
                (tipo = 'publicacion' AND id_publicacion IS NOT NULL AND id_persona IS NULL) OR
                (tipo = 'persona' AND id_persona IS NOT NULL AND id_publicacion IS NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('favorito');
    }
};
