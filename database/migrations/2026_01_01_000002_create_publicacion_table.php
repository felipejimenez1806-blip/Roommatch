<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicacion', function (Blueprint $table) {
            $table->increments('id_publicacion');
            $table->unsignedInteger('id_usuario')->comment('Oferente dueño de la publicación');
            $table->string('titulo', 200);
            $table->text('descripcion')->nullable();
            $table->decimal('precio', 10, 2);
            $table->enum('tipo_espacio', ['Habitación', 'Apartamento', 'Casa', 'Estudio']);
            $table->string('direccion', 255);
            $table->string('zona', 100);
            $table->string('ciudad', 100)->default('Bogotá');
            $table->enum('estado_inmueble', ['disponible', 'reservado', 'no_disponible'])->default('disponible');
            $table->enum('genero_preferido', ['masculino', 'femenino', 'otro'])->nullable();
            $table->string('img', 500)->nullable()->comment('Imagen principal / miniatura');
            $table->longText('imagenes')->nullable()->comment('Array JSON de URLs (galería completa)');
            $table->dateTime('fecha_publicacion')->useCurrent();
            $table->dateTime('fecha_actualizacion')->nullable();
            $table->date('fecha_disponible')->nullable();
            $table->softDeletes()->comment('Borrado lógico: si tiene fecha, la publicación se considera eliminada para el usuario pero el registro sigue en BD');

            $table->foreign('id_usuario', 'fk_pub_usuario')
                ->references('id_usuario')->on('usuario')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->index('id_usuario', 'idx_pub_usuario');
            $table->index('zona', 'idx_pub_zona');
            $table->index('tipo_espacio', 'idx_pub_tipo');
            $table->index('estado_inmueble', 'idx_pub_estado');
            $table->index('precio', 'idx_pub_precio');
        });

        DB::statement("ALTER TABLE publicacion ADD CONSTRAINT chk_pub_imagenes_json CHECK (JSON_VALID(imagenes) OR imagenes IS NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('publicacion');
    }
};
