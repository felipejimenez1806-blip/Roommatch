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
     * Requiere que RolSeeder ya haya corrido (debe llamarse antes en
     * DatabaseSeeder).
     */
    public function run(): void
    {
        $usuario = Usuario::firstOrCreate(
            ['correo' => 'roommatch.admin2026@gmail.com'],
            [
                'nombre_completo' => 'Administrador RoomMatch',
                'contrasena' => Hash::make('RoomMatchAdmin2026'),
                'proveedor' => 'local',
                'telefono' => null,
            ]
        );

        $rolAdmin = Rol::where('nombre', 'admin')->firstOrFail();

        $usuario->roles()->syncWithoutDetaching([
            $rolAdmin->id_rol => [
                'fecha_asignacion' => now(),
                'asignado_por' => null,
            ],
        ]);
    }
}
