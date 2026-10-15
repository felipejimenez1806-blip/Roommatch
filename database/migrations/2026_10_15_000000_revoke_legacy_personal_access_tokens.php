<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La autenticación pasó de tokens Bearer (Sanctum) a cookie de sesión.
 * Los tokens emitidos antes del cambio no expiran (sanctum.expiration = null),
 * así que se revocan todos. La tabla se deja en su sitio (inofensiva y vacía).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')->delete();
        }
    }

    public function down(): void
    {
        // Los tokens revocados no se pueden restaurar.
    }
};
