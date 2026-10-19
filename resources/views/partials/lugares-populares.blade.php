{{--
  partials/lugares-populares.blade.php

  Grilla de "Lugares populares" compartida por index y dashboard. $lugares la
  inyecta el View Composer de AppServiceProvider (App\Services\LocalidadPopularService):
  las 6 localidades más agendadas, ya ordenadas, siempre completas.

  La estructura es la de siempre (el CSS de index.css depende de ella): 4 hijos
  directos en .lugares-grid = tarjeta alta, columna con 2 bajas, tarjeta alta,
  columna con 2 bajas. La posición 0 es la más popular y ocupa la 1.ª tarjeta alta.
--}}
@php($lugares = collect($lugares ?? []))
@if ($lugares->isNotEmpty())
  <div class="lugares-grid">

    @if ($lugares->has(0))
      @include('partials.lugar-card', ['lugar' => $lugares[0], 'forma' => 'tall'])
    @endif

    <div style="display:flex;flex-direction:column;gap:10px;">
      @foreach ([1, 2] as $i)
        @if ($lugares->has($i))
          @include('partials.lugar-card', ['lugar' => $lugares[$i], 'forma' => 'short'])
        @endif
      @endforeach
    </div>

    @if ($lugares->has(3))
      @include('partials.lugar-card', ['lugar' => $lugares[3], 'forma' => 'tall'])
    @endif

    <div style="display:flex;flex-direction:column;gap:10px;">
      @foreach ([4, 5] as $i)
        @if ($lugares->has($i))
          @include('partials.lugar-card', ['lugar' => $lugares[$i], 'forma' => 'short'])
        @endif
      @endforeach
    </div>

  </div>
@endif
