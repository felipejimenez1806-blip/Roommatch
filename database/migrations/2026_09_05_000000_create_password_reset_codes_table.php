<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();

            // Hash del código de 6 dígitos (nunca se guarda en texto plano).
            $table->string('codigo');

            // Hash del token temporal que se entrega SOLO después de verificar
            // el código correctamente. Este token es el que autoriza el cambio
            // de contraseña en el paso 3, sin volver a exponer el código de 6 dígitos.
            $table->string('token_verificacion')->nullable();

            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamp('expira_en');
            $table->timestamp('verificado_en')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_codes');
    }
};
