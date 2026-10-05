<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte', function (Blueprint $table) {
            $table->increments('id_reporte');
            $table->unsignedInteger('id_usuario_reporta')->nullable();
            $table->enum('tipo', ['publicacion', 'persona', 'usuario']);
            $table->unsignedInteger('id_publicacion')->nullable();
            $table->unsignedInteger('id_persona')->nullable();
            $table->unsignedInteger('id_usuario_reportado')->nullable();
            $table->string('objetivo_titulo', 255)->nullable()
                ->comment('Snapshot del título/nombre al momento del reporte');
            $table->string('motivo', 150)
                ->comment('Ver MOTIVOS_REPORTE_PUBLICACION / MOTIVOS_REPORTE_ROOMIE en perfil.js');
            $table->text('descripcion')->nullable();
            $table->enum('estado_reporte', ['pendiente', 'resuelto', 'descartado'])->default('pendiente');
            $table->unsignedInteger('id_administrador')->nullable()->comment('Admin que lo gestionó');
            $table->dateTime('fecha_reporte')->useCurrent();
            $table->dateTime('fecha_gestion')->nullable();

            // IMPORTANTE: todas estas FK usan 'set null' (no 'cascade').
            // Un reporte es un registro de auditoría/moderación: si se
            // elimina la publicación, el usuario reportado, la persona
            // roomie, o incluso la cuenta de quien reportó, el reporte
            // debe conservarse (con objetivo_titulo como snapshot) en
            // vez de desaparecer en cascada.
            $table->foreign('id_usuario_reporta', 'fk_rep_usuario_reporta')
                ->references('id_usuario')->on('usuario')
                ->onDelete('set null')->onUpdate('cascade');
            $table->foreign('id_publicacion', 'fk_rep_publicacion')
                ->references('id_publicacion')->on('publicacion')
                ->onDelete('set null')->onUpdate('cascade');
            $table->foreign('id_persona', 'fk_rep_persona')
                ->references('id_persona')->on('persona_roomie')
                ->onDelete('set null')->onUpdate('cascade');
            $table->foreign('id_usuario_reportado', 'fk_rep_usuario_reportado')
                ->references('id_usuario')->on('usuario')
                ->onDelete('set null')->onUpdate('cascade');
            $table->foreign('id_administrador', 'fk_rep_admin')
                ->references('id_usuario')->on('usuario')
                ->onDelete('set null')->onUpdate('cascade');

            $table->index('id_usuario_reporta', 'idx_rep_usuario_reporta');
            $table->index('id_publicacion', 'idx_rep_publicacion');
            $table->index('id_persona', 'idx_rep_persona');
            $table->index('id_usuario_reportado', 'idx_rep_usuario_reportado');
            $table->index('id_administrador', 'idx_rep_admin');
            $table->index('estado_reporte', 'idx_rep_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte');
    }
};
