<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caracteristicas_publicacion', function (Blueprint $table) {
            $table->unsignedInteger('id_publicacion')->comment('PK y FK a la vez');

            $table->boolean('amueblado')->default(false);
            $table->boolean('bano_privado')->default(false);
            $table->boolean('cocina_compartida')->default(false);
            $table->boolean('lavadora')->default(false);
            $table->boolean('secadora')->default(false);
            $table->boolean('parqueadero')->default(false);
            $table->boolean('balcon')->default(false);
            $table->boolean('terraza')->default(false);
            $table->boolean('incluye_agua')->default(false);
            $table->boolean('incluye_luz')->default(false);
            $table->boolean('incluye_internet')->default(false);
            $table->boolean('incluye_gas')->default(false);
            $table->text('detalles_incluidos')->nullable();
            $table->integer('numero_habitantes')->nullable();
            $table->enum('ambiente_hogar', ['estudiantes', 'profesionales', 'otro'])->nullable();
            $table->boolean('fumadores_en_casa')->default(false);
            $table->boolean('mascotas_en_casa')->default(false);
            $table->enum('tamano_habitacion', ['pequena', 'mediana', 'grande'])->nullable();
            $table->enum('tipo_cama', ['sencilla', 'semidoble', 'doble', 'queen', 'king'])->nullable();
            $table->boolean('habitacion_compartida')->default(false);
            $table->boolean('permite_mascotas')->default(false);
            $table->boolean('permite_visitas')->default(false);
            $table->boolean('permite_fumar')->default(false);
            $table->boolean('permite_fiestas')->default(false);
            $table->boolean('permite_parejas')->default(false);
            $table->string('horario_silencio', 50)->nullable()->comment('Ej: 10:00pm - 7:00am');
            $table->string('horario_entrada', 50)->nullable()->comment('Ej: Hasta las 11:00pm');
            $table->boolean('cerca_transporte_publico')->default(false);
            $table->string('distancia_transporte', 50)->nullable()->comment('Texto libre, ej: "250 metros"');
            $table->boolean('porteria')->default(false);
            $table->boolean('camaras_seguridad')->default(false);
            $table->boolean('espacio_trabajo')->default(false);
            $table->boolean('ascensor')->default(false);
            $table->boolean('gimnasio')->default(false);
            $table->boolean('zona_comun')->default(false);
            $table->boolean('cerca_supermercado')->default(false);
            $table->boolean('cerca_universidad')->default(false);

            $table->primary('id_publicacion');
            $table->foreign('id_publicacion', 'fk_caract_pub_publicacion')
                ->references('id_publicacion')->on('publicacion')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->index('permite_mascotas', 'idx_caract_pub_mascotas');
            $table->index('amueblado', 'idx_caract_pub_amueblado');
            $table->index('bano_privado', 'idx_caract_pub_bano');
            $table->index('parqueadero', 'idx_caract_pub_parqueadero');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caracteristicas_publicacion');
    }
};
