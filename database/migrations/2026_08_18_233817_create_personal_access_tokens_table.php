<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tokens de Sanctum. Cada token pertenece a un usuario (1 usuario : N tokens).
     *
     * Se reemplaza $table->morphs('tokenable') porque crea tokenable_id como
     * BIGINT UNSIGNED, tipo incompatible con usuario.id_usuario (INT UNSIGNED)
     * y por eso no admitía llave foránea. Sanctum solo autentica el modelo
     * Usuario, así que la relación real es una FK normal.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedInteger('tokenable_id')->comment('FK -> usuario.id_usuario');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            // Mismo índice que generaba morphs(), Sanctum lo usa para buscar tokens.
            $table->index(['tokenable_type', 'tokenable_id']);

            $table->foreign('tokenable_id', 'fk_pat_usuario')
                ->references('id_usuario')->on('usuario')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
