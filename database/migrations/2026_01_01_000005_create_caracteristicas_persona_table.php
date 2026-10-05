<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caracteristicas_persona', function (Blueprint $table) {
            $table->unsignedInteger('id_persona');

            $table->boolean('fumador')->default(false);
            $table->boolean('tiene_mascota')->default(false);
            $table->boolean('ordenado')->default(false);
            $table->boolean('sociable')->default(false);
            $table->boolean('madrugador')->default(false);
            $table->boolean('trasnochador')->default(false);
            $table->boolean('fiestero')->default(false);
            $table->enum('ambiente_preferido', ['estudiantes', 'profesionales', 'otro'])->nullable();
            $table->boolean('acepta_mascotas')->default(false);
            $table->boolean('acepta_fumadores')->default(false);
            $table->boolean('acepta_visitas')->default(false);
            $table->boolean('acepta_parejas')->default(false);
            $table->enum('horario', ['diurno', 'nocturno', 'mixto'])->nullable();
            $table->boolean('trabaja_desde_casa')->default(false);
            $table->boolean('viaja_frecuentemente')->default(false);
            $table->enum('tiempo_busqueda', ['corto', 'largo'])->nullable();
            $table->enum('fecha_mudanza', ['inmediata', '1mes', 'flexible'])->nullable();
            $table->boolean('quiere_amueblado')->default(false);
            $table->boolean('quiere_bano_privado')->default(false);
            $table->boolean('quiere_parqueadero')->default(false);
            $table->boolean('cerca_universidad')->default(false);
            $table->boolean('cerca_transporte_publico')->default(false);
            $table->boolean('tiene_vehiculo')->default(false);
            $table->boolean('comparte_gastos')->default(false);
            $table->boolean('referencias_verificadas')->default(false);

            $table->primary('id_persona');
            $table->foreign('id_persona', 'fk_caract_persona_persona')
                ->references('id_persona')->on('persona_roomie')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caracteristicas_persona');
    }
};
