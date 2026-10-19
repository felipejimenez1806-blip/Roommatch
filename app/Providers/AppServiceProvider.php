<?php

namespace App\Providers;

use App\Models\Localidad;
use App\Models\PersonaRoomie;
use App\Models\Publicacion;
use App\Models\Reporte;
use App\Models\Usuario;
use App\Policies\PersonaRoomiePolicy;
use App\Policies\PublicacionPolicy;
use App\Policies\ReportePolicy;
use App\Services\LocalidadPopularService;
use App\Support\Permisos;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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
        Gate::policy(PersonaRoomie::class, PersonaRoomiePolicy::class);

        // "Lugares populares" de index y dashboard: el composer va en el
        // partial (no en las dos vistas) para que ambas compartan la misma
        // lógica y solo se calcule donde realmente se incluye.
        View::composer('partials.lugares-populares', function ($vista) {
            $vista->with('lugares', app(LocalidadPopularService::class)->populares());
        });

        // Filtro de zona de Habitaciones: <select> cerrado con las localidades.
        View::composer('habitaciones', function ($vista) {
            $vista->with('localidades', Localidad::todas());
        });
    }
}
