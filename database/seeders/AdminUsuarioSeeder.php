<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUsuarioSeeder extends Seeder
{
    /**
     * Equivalente a seedAdminUserIfMissing() en login.js.
     * Crea el admin solo si no existe todavía (evita duplicados al re-sembrar)
     * y le asigna únicamente el rol 'admin' (sin 'cliente'): es la cuenta de
     * sistema "Administrador RoomMatch", sin selector "Ingresar como…".
     *
     * Es SUPERADMIN (es_superadmin = true, correo @roommatch.com): solo otro
     * superadmin puede bloquearla y no puede cambiar de rol.
     *
     * Requiere que RolSeeder ya haya corrido (debe llamarse antes en
     * DatabaseSeeder).
     */
    public function run(): void
    {
        $usuario = Usuario::firstOrCreate(
            ['correo' => 'roommatch.admin2026@roommatch.com'],
            [
                'nombre_completo' => 'Administrador RoomMatch',
                'contrasena' => Hash::make('RoomMatchAdmin2026'),
                'proveedor' => 'local',
                'telefono' => null,
            ]
        );

        // es_superadmin no es fillable a propósito (nadie debe poder marcarlo
        // por asignación masiva): se asigna explícitamente. Idempotente.
        if (! $usuario->es_superadmin) {
            $usuario->es_superadmin = true;
            $usuario->save();
        }

        $rolAdmin = Rol::where('nombre', 'admin')->firstOrFail();

        $usuario->roles()->syncWithoutDetaching([
            $rolAdmin->id_rol => [
                'fecha_asignacion' => now(),
                'asignado_por' => null,
            ],
        ]);
    }
}
