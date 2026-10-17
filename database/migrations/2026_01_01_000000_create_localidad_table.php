<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Catálogo cerrado de las 20 localidades de Bogotá.
 *
 * Es la ÚNICA fuente de verdad de los nombres de zona: los FormRequests
 * validan contra esta tabla y los <select> de los formularios (crear
 * publicación, crear perfil de roomie, filtro de habitaciones) se llenan
 * desde ella. Por eso publicacion.zona y persona_roomie.zona siguen siendo
 * texto pero siempre coinciden EXACTAMENTE con localidad.nombre.
 *
 * Los datos se insertan aquí (no en un seeder) porque son datos de
 * referencia: con un simple `php artisan migrate` el sitio queda
 * funcionando, sin depender de correr `db:seed`.
 *
 * orden_defecto: las 6 localidades que se muestran en index/dashboard cuando
 * no hay suficiente actividad (1 = la más importante, ocupa la tarjeta
 * grande). NULL = no es de las "por defecto".
 */
return new class extends Migration
{
    private const DEFECTO = [
        'Chapinero' => 1,
        'Kennedy' => 2,
        'Santa Fe' => 3,
        'Puente Aranda' => 4,
        'Suba' => 5,
        'Teusaquillo' => 6,
    ];

    private const NOMBRES = [
        'Antonio Nariño', 'Barrios Unidos', 'Bosa', 'Chapinero', 'Ciudad Bolívar',
        'Engativá', 'Fontibón', 'Kennedy', 'La Candelaria', 'Los Mártires',
        'Puente Aranda', 'Rafael Uribe Uribe', 'San Cristóbal', 'Santa Fe', 'Suba',
        'Sumapaz', 'Teusaquillo', 'Tunjuelito', 'Usaquén', 'Usme',
    ];

    public function up(): void
    {
        Schema::create('localidad', function (Blueprint $table) {
            $table->increments('id_localidad');
            $table->string('nombre', 100)->unique()->comment('Nombre exacto: es el valor que se guarda en publicacion.zona y persona_roomie.zona');
            $table->string('slug', 100)->unique()->comment('Para URLs (?localidad=suba) y para el archivo public/img/localidades/{slug}.webp');
            $table->string('imagen', 150)->nullable()->comment('Archivo dentro de public/img/localidades/');
            $table->unsignedTinyInteger('orden_defecto')->nullable()->comment('1-6 = localidad por defecto en index/dashboard; NULL = no');

            $table->index('orden_defecto', 'idx_localidad_orden');
        });

        DB::table('localidad')->insertOrIgnore(array_map(fn (string $nombre) => [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'imagen' => Str::slug($nombre) . '.webp',
            'orden_defecto' => self::DEFECTO[$nombre] ?? null,
        ], self::NOMBRES));
    }

    public function down(): void
    {
        Schema::dropIfExists('localidad');
    }
};
