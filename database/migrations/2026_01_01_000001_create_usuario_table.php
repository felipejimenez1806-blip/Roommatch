<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario', function (Blueprint $table) {
            $table->increments('id_usuario');
            // tipo_usuario (ENUM cliente/admin) se retira: el rol ahora vive
            // en las tablas rol / usuario_rol (relación N:N). Ver migraciones
            // 2026_09_22_000001_create_rol_table y
            // 2026_09_22_000002_create_usuario_rol_table.
            $table->string('area', 100)->nullable()
                ->comment('Área/departamento del administrador. NULL para cuentas sin rol admin.');
            $table->string('cargo', 100)->nullable()
                ->comment('Cargo del administrador. NULL para cuentas sin rol admin.');
            $table->string('nombre_completo', 150);
            $table->string('correo', 150);
            $table->string('contrasena')->nullable()
                ->comment('Hashear siempre con Hash::make. NULL si login social');
            $table->enum('proveedor', ['local', 'google', 'facebook'])->default('local');
            $table->string('proveedor_id', 191)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('direccion')->nullable();
            $table->enum('genero', ['masculino', 'femenino', 'otro', 'prefiero_no_decir'])->nullable();
            $table->string('foto_perfil', 500)->nullable();
            $table->json('preferencias_convivencia')->nullable();
            $table->boolean('bloqueado')->default(false);
            $table->string('bloqueado_motivo')->nullable();
            $table->dateTime('bloqueado_fecha')->nullable();
            $table->unsignedTinyInteger('intentos_fallidos')->default(0)
                ->comment('Contador de contraseñas incorrectas seguidas; se resetea a 0 en login exitoso, bloquea la cuenta al llegar a 5');
            $table->dateTime('terminos_aceptados_en')->nullable()
                ->comment('Evidencia legal de aceptación de términos y condiciones. NULL solo debería darse en cuentas creadas antes de este campo o por seeders internos');
            $table->dateTime('fecha_registro')->useCurrent();

            $table->unique('correo', 'uq_usuario_correo');
            $table->unique(['proveedor', 'proveedor_id'], 'uq_usuario_proveedor');
        });

        // CHECK: cuentas locales requieren contraseña, cuentas OAuth requieren proveedor_id
        DB::statement("
            ALTER TABLE usuario ADD CONSTRAINT chk_usuario_auth CHECK (
                (proveedor = 'local' AND contrasena IS NOT NULL) OR
                (proveedor IN ('google','facebook') AND proveedor_id IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario');
    }
};
