<?php

namespace App\Providers;

use App\Models\Publicacion;
use App\Models\Reporte;
use App\Models\Usuario;
use App\Policies\PublicacionPolicy;
use App\Policies\ReportePolicy;
use App\Support\Permisos;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Un Gate por cada permiso de App\Support\Permisos::MAPA. Se usan en
        // las rutas con el middleware `can:` y dentro de los controladores
        // con Gate::authorize(). El invitado (sin sesión) nunca pasa: el
        // closure exige un Usuario.
        foreach (Permisos::todos() as $permiso) {
            Gate::define($permiso, fn (Usuario $usuario) => Permisos::permitido($usuario, $permiso));
        }

        // Reglas que dependen del recurso concreto (estado del reporte, si la
        // publicación ya está bajada...). Se registran a mano para no depender
        // del autodescubrimiento por nombre.
        Gate::policy(Reporte::class, ReportePolicy::class);
        Gate::policy(Publicacion::class, PublicacionPolicy::class);
    }
}
