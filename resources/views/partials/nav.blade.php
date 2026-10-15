<nav>
  <a class="logo" href="{{ route('index') }}">
    <img src="{{ asset('Img/logo_roommatch.png') }}" alt="Roommatch" class="logo-img"/>
  </a>
  <ul class="nav-links">
    <li><a href="{{ url('/habitaciones') }}">Habitaciones</a></li>
    <li><a href="{{ url('/roomies') }}">Roomies</a></li>
    <li><a id="navPopularLink" href="#popularesSection">Popular</a></li>
  </ul>
  {{-- Acciones de sesión: las pinta el servidor según haya o no sesión.
       nav.js solo les engancha los clics (cambiar de rol, usar como
       cliente) y pide el contador de notificaciones. --}}
  <div class="nav-actions" id="navActions">
    @auth
      @php
        $usuarioNav = auth()->user();
        $usuarioNav->loadMissing('roles');
        $rolesNav = $usuarioNav->roles->pluck('nombre');
        $primerNombreNav = preg_split('/\s+/', trim($usuarioNav->nombre_completo ?: 'Usuario'))[0];
        $inicialNav = mb_strtoupper(mb_substr(trim($usuarioNav->nombre_completo ?: 'U'), 0, 1));
        $tieneVariosRolesNav = $rolesNav->count() > 1;
        $esSoloAdminNav = $rolesNav->contains('admin') && ! $rolesNav->contains('cliente');
      @endphp

      {{-- "Cambiar de rol" solo tiene sentido con más de un rol asignado. --}}
      @if ($tieneVariosRolesNav)
        <button type="button" class="btn-outline" id="navCambiarRolBtn" title="Cambiar de rol">Cambiar de rol</button>
      @endif

      {{-- Admin sin rol cliente todavía: autoservicio, sin contraseña. --}}
      @if ($esSoloAdminNav)
        <button type="button" class="btn-outline" id="navUsarClienteBtn" title="Usar también como cliente">Usar también como cliente</button>
      @endif

      <a class="nav-bell" id="navBell" href="{{ url('/perfil#panelNotificaciones') }}" title="Notificaciones">
        <svg class="nav-bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span class="nav-bell-badge" id="navBellBadge" hidden>0</span>
      </a>
      <a class="user-menu-trigger" id="userMenuTrigger" href="{{ route('perfil') }}" title="Mi perfil">
        <span class="user-avatar" id="userAvatar">
          @if ($usuarioNav->foto_perfil)
            <img src="{{ $usuarioNav->foto_perfil }}" alt="{{ $primerNombreNav }}"/>
          @else
            {{ $inicialNav }}
          @endif
        </span>
        <span class="user-name" id="userName">{{ $primerNombreNav }}</span>
      </a>
    @else
      <a class="btn-outline" href="{{ route('login') }}">Iniciar sesión</a>
      <a class="btn-solid" href="{{ route('signup') }}">Registrarse</a>
    @endauth
  </div>
</nav>
