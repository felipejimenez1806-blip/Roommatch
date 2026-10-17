{{--
  partials/lugar-card.blade.php — una tarjeta de "Lugares populares".
  Es un <a> real (no un div con JS): funciona aunque el JS falle, se puede abrir
  en otra pestaña y los buscadores ven el enlace.
    $lugar  ['nombre' => ..., 'slug' => ...]
    $forma  'tall' (tarjeta alta) | 'short' (tarjeta baja, va apilada de a dos)
--}}
<a class="lugar-card {{ $forma }}"
   href="{{ \App\Models\Localidad::urlHabitaciones($lugar['slug']) }}"
   aria-label="Ver habitaciones en {{ $lugar['nombre'] }}">
  <img src="{{ \App\Models\Localidad::urlImagen($lugar['slug']) }}"
       alt="{{ $lugar['nombre'] }}, Bogotá"
       width="600" height="450" loading="lazy"
       @if ($forma === 'tall') style="height:270px" @endif/>
  <span class="lugar-label">{{ $lugar['nombre'] }}</span>
</a>
