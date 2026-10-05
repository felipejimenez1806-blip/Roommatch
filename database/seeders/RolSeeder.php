<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    /**
     * Debe correr ANTES que AdminUsuarioSeeder (necesita que el rol
     * 'admin' ya exista para poder asignarlo). Ver nota en
     * DatabaseSeeder sobre el orden de llamada.
     */
    public function run(): void
    {
        $roles = [
            ['nombre' => 'cliente', 'descripcion' => 'Rol por defecto de todo usuario registrado.'],
            ['nombre' => 'admin', 'descripcion' => 'Acceso al panel de administración.'],
        ];

        foreach ($roles as $rol) {
            Rol::firstOrCreate(['nombre' => $rol['nombre']], $rol);
        }
    }
}
