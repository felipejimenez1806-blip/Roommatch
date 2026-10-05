<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de roles. Nace con solo dos filas (cliente, admin), sembradas
 * por RolSeeder. tinyIncrements es intencional: esta tabla no está
 * pensada para crecer, es un catálogo fijo de RBAC mínimo, no un sistema
 * de permisos granulares.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rol', function (Blueprint $table) {
            $table->tinyIncrements('id_rol');
            $table->string('nombre', 30);
            $table->string('descripcion', 255)->nullable();

            $table->unique('nombre', 'uq_rol_nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol');
    }
};
