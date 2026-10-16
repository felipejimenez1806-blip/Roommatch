<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensajes del formulario de contacto (público).
 *
 * Ya no es una tabla aislada. Las dos FK son opcionales (NULL):
 *  - id_usuario: solo se llena si quien escribe tenía sesión iniciada.
 *  - id_administrador: se llena cuando un admin atiende el mensaje.
 * ON DELETE SET NULL: el mensaje se conserva aunque se borre la cuenta del
 * remitente o del administrador (nombre y correo siguen guardados).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes_contacto', function (Blueprint $table) {
            $table->id('id_mensaje_contacto');
            $table->unsignedInteger('id_usuario')->nullable()
                ->comment('Remitente si tenía sesión iniciada. NULL = visitante sin cuenta');
            $table->unsignedInteger('id_administrador')->nullable()
                ->comment('Admin o moderador que atendió el mensaje');
            $table->string('nombre', 100);
            $table->string('correo', 150);
            $table->string('asunto', 30);
            $table->text('mensaje');
            $table->boolean('atendido')->default(false);
            $table->dateTime('fecha_atencion')->nullable()
                ->comment('Cuándo lo atendió el administrador o moderador');
            $table->text('respuesta')->nullable()
                ->comment('Texto de la respuesta enviada por correo al remitente. NULL = se marcó atendido sin responder por correo');
            $table->timestamps();

            $table->foreign('id_usuario', 'fk_contacto_usuario')
                ->references('id_usuario')->on('usuario')
                ->onDelete('set null')->onUpdate('cascade');
            $table->foreign('id_administrador', 'fk_contacto_admin')
                ->references('id_usuario')->on('usuario')
                ->onDelete('set null')->onUpdate('cascade');

            $table->index('id_usuario', 'idx_contacto_usuario');
            $table->index('id_administrador', 'idx_contacto_admin');
            $table->index('atendido', 'idx_contacto_atendido');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes_contacto');
    }
};
